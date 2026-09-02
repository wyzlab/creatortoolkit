<?php
/**
 * gates.php — gate progress recount and the one-transaction gate close.
 * Called from complete-tool (when the last tool in a gate finishes) and from
 * complete-gate. Idempotent: closing an already-closed gate returns its stored
 * result without burning a second WyzAI slot or re-sending email.
 *
 * Must be called INSIDE the caller's transaction.
 */

declare(strict_types=1);

require_once __DIR__ . '/tools.php';
require_once __DIR__ . '/results.php';
require_once __DIR__ . '/wyzai.php';
require_once __DIR__ . '/mailer.php';

/** Recount completed tools in a gate and store it. Returns [completed, required]. */
function recount_gate(PDO $pdo, int $userId, int $gate): array
{
    $need = GATES[$gate]['tools_required'];
    $slugs = array_keys(tools_in_gate($gate));
    $in = implode(',', array_fill(0, count($slugs), '?'));
    $sql = "SELECT COUNT(*) FROM tool_sessions
             WHERE user_id = ? AND status = 'completed' AND tool_slug IN ($in)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$userId], $slugs));
    $done = (int)$stmt->fetchColumn();

    $upd = $pdo->prepare(
        'UPDATE gate_progress SET tools_completed = ? WHERE user_id = ? AND gate_number = ?'
    );
    $upd->execute([$done, $userId, $gate]);
    return [$done, $need];
}

/**
 * Close a gate if its tools are all complete. Returns an array with the
 * summary and the WyzAI handover, or null when the gate is not ready.
 */
function close_gate(PDO $pdo, int $userId, int $gate): ?array
{
    // Already closed? Return stored result plus the code already claimed.
    $gr = $pdo->prepare('SELECT summary_html, summary_json, ai_paragraph FROM gate_results
                          WHERE user_id = ? AND gate_number = ? LIMIT 1');
    $gr->execute([$userId, $gate]);
    if ($existing = $gr->fetch()) {
        $claim = wyzai_claim($pdo, $userId, 'gate_' . $gate);
        $nextUnlocked = isset(GATES[$gate + 1]) ? gate_unlocked_bool($pdo, $userId, $gate + 1) : false;
        return [
            'summary_html' => $existing['summary_html'],
            'ai_paragraph' => $existing['ai_paragraph'],
            'wyzai_code'   => $claim['code'] ?? null,
            'coach_name'   => $claim['coach_name'] ?? null,
            'next_gate_unlocked' => $nextUnlocked,
            'already' => true,
        ];
    }

    [$done, $need] = recount_gate($pdo, $userId, $gate);
    if ($done < $need) {
        return null;   // not ready
    }

    $nowStr = now_dt();

    // Mark the gate complete.
    $pdo->prepare('UPDATE gate_progress SET completed_at = ? WHERE user_id = ? AND gate_number = ?')
        ->execute([$nowStr, $userId, $gate]);

    // Load the profile to build the summary.
    $pf = $pdo->prepare('SELECT profile_json FROM user_profile WHERE user_id = ? LIMIT 1');
    $pf->execute([$userId]);
    $profile = json_decode((string)$pf->fetchColumn() ?: '{}', true) ?: [];
    [$summaryJson, $summaryHtml] = build_gate_summary($gate, $profile);

    // Claim the coach code for this gate (idempotent, one slot).
    $claim = wyzai_claim($pdo, $userId, 'gate_' . $gate);

    // Unlock the next gate.
    $nextUnlocked = false;
    if (isset(GATES[$gate + 1])) {
        $pdo->prepare('UPDATE gate_progress SET unlocked_at = COALESCE(unlocked_at, ?)
                        WHERE user_id = ? AND gate_number = ?')
            ->execute([$nowStr, $userId, $gate + 1]);
        $nextUnlocked = true;
    }

    // Store the gate result.
    $pdo->prepare('INSERT INTO gate_results (user_id, gate_number, summary_json, summary_html, ai_paragraph, created_at)
                   VALUES (?, ?, ?, ?, NULL, ?)
                   ON DUPLICATE KEY UPDATE summary_json = VALUES(summary_json), summary_html = VALUES(summary_html)')
        ->execute([$userId, $gate, json_encode($summaryJson), $summaryHtml, $nowStr]);

    // Log the AI call as skipped until a provider is configured (Stage E).
    $aiCfg = require CONFIG_DIR . '/ai.php';
    if (empty($aiCfg['enabled'])) {
        $pdo->prepare('INSERT IGNORE INTO ai_log (user_id, trigger_key, status, created_at)
                       VALUES (?, ?, "skipped", ?)')
            ->execute([$userId, 'gate_' . $gate, $nowStr]);
    }

    // Queue the gate-complete email (sent for real at Stage E).
    queue_gate_email($pdo, $userId, $gate, $summaryHtml, $claim);

    // The last gate finishing means the whole toolkit is done: close the package.
    if (!isset(GATES[$gate + 1])) {
        close_package($pdo, $userId);
    }

    return [
        'summary_html' => $summaryHtml,
        'ai_paragraph' => null,
        'wyzai_code'   => $claim['code'] ?? null,
        'coach_name'   => $claim['coach_name'] ?? null,
        'next_gate_unlocked' => $nextUnlocked,
        'already' => false,
    ];
}

/**
 * Close the full package (gate_number 0): claim the Community Designer code,
 * store the package summary, and queue the package email. Idempotent.
 * Call inside a transaction.
 */
function close_package(PDO $pdo, int $userId): ?array
{
    $gr = $pdo->prepare('SELECT summary_html FROM gate_results WHERE user_id = ? AND gate_number = 0 LIMIT 1');
    $gr->execute([$userId]);
    $existing = $gr->fetch();

    $claim = wyzai_claim($pdo, $userId, 'package');

    if ($existing) {
        return ['summary_html' => $existing['summary_html'],
                'wyzai_code' => $claim['code'] ?? null, 'coach_name' => $claim['coach_name'] ?? null];
    }

    $nowStr = now_dt();
    $pf = $pdo->prepare('SELECT profile_json FROM user_profile WHERE user_id = ? LIMIT 1');
    $pf->execute([$userId]);
    $profile = json_decode((string)$pf->fetchColumn() ?: '{}', true) ?: [];
    [$sumJson, $sumHtml] = build_gate_summary(0, $profile);

    $pdo->prepare('INSERT INTO gate_results (user_id, gate_number, summary_json, summary_html, ai_paragraph, created_at)
                   VALUES (?, 0, ?, ?, NULL, ?)
                   ON DUPLICATE KEY UPDATE summary_html = VALUES(summary_html)')
        ->execute([$userId, json_encode($sumJson), $sumHtml, $nowStr]);

    $aiCfg = require CONFIG_DIR . '/ai.php';
    if (empty($aiCfg['enabled'])) {
        $pdo->prepare('INSERT IGNORE INTO ai_log (user_id, trigger_key, status, created_at) VALUES (?, "package", "skipped", ?)')
            ->execute([$userId, $nowStr]);
    }

    // Queue the package-complete email.
    $u = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    $u->execute([$userId]);
    $email = (string)$u->fetchColumn();
    if ($email !== '') {
        $coach = $claim['coach_name'] ?? '';
        $code  = $claim['code'] ?? '';
        $codeLine = ($code !== '' && strpos($code, 'PLACEHOLDER') === false) ? '<p>Your <strong>' . e($coach) . '</strong> code is <strong>' . e($code) . '</strong>.</p>' : '';
        $subject = 'You finished the DIY Creator Starter Toolkit';
        $html = $sumHtml . $codeLine . '<p><a href="' . e(APP['app_url']) . '/results/package.php">Open your full package</a></p>';
        $text = strip_tags($sumHtml) . "\n\nOpen your full package: " . APP['app_url'] . "/results/package.php\n";
        mail_queue('package_complete', $email, $subject, $html, $text, $userId);
    }

    return ['summary_html' => $sumHtml, 'wyzai_code' => $claim['code'] ?? null, 'coach_name' => $claim['coach_name'] ?? null];
}

/** Is a gate unlocked for a user? (small helper, no include of guard needed) */
function gate_unlocked_bool(PDO $pdo, int $userId, int $gate): bool
{
    $s = $pdo->prepare('SELECT unlocked_at FROM gate_progress WHERE user_id = ? AND gate_number = ? LIMIT 1');
    $s->execute([$userId, $gate]);
    $r = $s->fetch();
    return $r && $r['unlocked_at'] !== null;
}

/** Queue the gate-complete email with unlocked PDFs and the coach code. */
function queue_gate_email(PDO $pdo, int $userId, int $gate, string $summaryHtml, ?array $claim): void
{
    $u = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    $u->execute([$userId]);
    $email = (string)$u->fetchColumn();
    if ($email === '') return;

    $label = GATES[$gate]['label'] ?? ('Gate ' . $gate);
    $coach = $claim['coach_name'] ?? '';
    $code  = $claim['code'] ?? '';
    $codeLine = ($code !== '' && strpos($code, 'PLACEHOLDER') === false)
        ? "Your $coach code is $code.\n" : '';
    $codeLineHtml = ($code !== '' && strpos($code, 'PLACEHOLDER') === false)
        ? '<p>Your <strong>' . e($coach) . '</strong> code is <strong>' . e($code) . '</strong>.</p>' : '';

    $subject = 'You cleared ' . $label . ' in your DIY Creator Starter Toolkit';
    $html = '<div style="font-family:Inter,Arial,sans-serif;max-width:600px">'
          . $summaryHtml . $codeLineHtml
          . '<p><a href="' . e(APP['app_url']) . '/dashboard.php">Back to your dashboard</a></p></div>';
    $text = strip_tags($summaryHtml) . "\n\n" . $codeLine
          . 'Dashboard: ' . APP['app_url'] . "/dashboard.php\n";

    mail_queue('gate_' . $gate . '_complete', $email, $subject, $html, $text, $userId);
}
