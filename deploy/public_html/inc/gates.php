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
require_once __DIR__ . '/ai.php';

/** Recount completed tools in a gate and store it. Returns [completed, required]. */
function recount_gate(PDO $pdo, int $userId, int $gate): array
{
    $need = GATES[$gate]['tools_required'];
    $slugs = array_keys(tools_in_gate($gate));
    $in = implode(',', array_fill(0, count($slugs), '?'));
    $sql = "SELECT tool_slug FROM tool_sessions
             WHERE user_id = ? AND status = 'completed' AND tool_slug IN ($in)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$userId], $slugs));
    $completed = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    // Collapse "pick one" groups: a group with any completed tool fills one slot,
    // and its members do not also count individually.
    $groups  = gate_choose_one_groups($gate);
    $grouped = gate_choose_one_slugs($gate);
    $done = 0;
    foreach ($completed as $s) { if (!in_array($s, $grouped, true)) { $done++; } }
    foreach ($groups as $group) { if (array_intersect($group, $completed)) { $done++; } }

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

    // Store the gate result. The AI paragraph is written post-commit by
    // finalize_gate_after_commit() so a slow AI call never holds a row lock.
    $pdo->prepare('INSERT INTO gate_results (user_id, gate_number, summary_json, summary_html, ai_paragraph, created_at)
                   VALUES (?, ?, ?, ?, NULL, ?)
                   ON DUPLICATE KEY UPDATE summary_json = VALUES(summary_json), summary_html = VALUES(summary_html)')
        ->execute([$userId, $gate, json_encode($summaryJson), $summaryHtml, $nowStr]);

    // When the last gate finishes, the whole toolkit is done — but the package
    // is closed post-commit by finalize_gate_after_commit(), so its email and
    // AI note are sent exactly once (closing it here would swallow that email).

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
 * Close the full package (gate_number 0): claim the Community Designer code and
 * store the package summary. Idempotent. Call inside a transaction. The AI note
 * and package email are sent post-commit by finalize_package_after_commit().
 */
function close_package(PDO $pdo, int $userId): ?array
{
    $gr = $pdo->prepare('SELECT summary_html, ai_paragraph FROM gate_results WHERE user_id = ? AND gate_number = 0 LIMIT 1');
    $gr->execute([$userId]);
    $existing = $gr->fetch();

    $claim = wyzai_claim($pdo, $userId, 'package');

    if ($existing) {
        return ['summary_html' => $existing['summary_html'],
                'ai_paragraph' => $existing['ai_paragraph'],
                'wyzai_code' => $claim['code'] ?? null, 'coach_name' => $claim['coach_name'] ?? null,
                'already' => true];
    }

    $nowStr = now_dt();
    $pf = $pdo->prepare('SELECT profile_json FROM user_profile WHERE user_id = ? LIMIT 1');
    $pf->execute([$userId]);
    $profile = json_decode((string)$pf->fetchColumn() ?: '{}', true) ?: [];
    [$sumJson, $sumHtml] = build_gate_summary(0, $profile);

    // The AI note and the package email are sent post-commit by
    // finalize_package_after_commit().
    $pdo->prepare('INSERT INTO gate_results (user_id, gate_number, summary_json, summary_html, ai_paragraph, created_at)
                   VALUES (?, 0, ?, ?, NULL, ?)
                   ON DUPLICATE KEY UPDATE summary_html = VALUES(summary_html)')
        ->execute([$userId, json_encode($sumJson), $sumHtml, $nowStr]);

    return ['summary_html' => $sumHtml, 'ai_paragraph' => null,
            'wyzai_code' => $claim['code'] ?? null, 'coach_name' => $claim['coach_name'] ?? null,
            'already' => false];
}

/**
 * Post-commit finalizer for a gate: called AFTER the gate-close transaction has
 * committed. On a fresh close it generates the AI coach note (best effort) and
 * queues the gate email with that note included; on the last gate it also
 * finalizes the package. Idempotent — a revisit (already closed) does nothing
 * and simply returns the handover it was given.
 */
function finalize_gate_after_commit(PDO $pdo, int $userId, int $gate, array $handover): array
{
    if (!empty($handover['already'])) {
        return $handover;   // AI note + email already handled on first close
    }

    $ai = ai_generate_for_gate($pdo, $userId, $gate);
    $handover['ai_paragraph'] = $ai;

    queue_gate_email($pdo, $userId, $gate, $handover['summary_html'], $ai,
                     ['code' => $handover['wyzai_code'] ?? null, 'coach_name' => $handover['coach_name'] ?? null]);

    // Finishing the last gate finishes the whole toolkit: close the package,
    // write its note, and send its email — all post-commit, exactly once.
    if (!isset(GATES[$gate + 1])) {
        finalize_package_after_commit($pdo, $userId);
    }
    return $handover;
}

/**
 * Post-commit finalizer for the full package (gate 0). Ensures the package is
 * closed (idempotent), generates its AI note, and queues the package email
 * exactly once. Returns the package handover.
 */
function finalize_package_after_commit(PDO $pdo, int $userId): ?array
{
    // Ensure the package result row exists (idempotent). Runs in its own tx so
    // it is safe to call standalone from results/package.php.
    try {
        $pdo->beginTransaction();
        $handover = close_package($pdo, $userId);
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return null;
    }
    if ($handover === null) return null;

    $ai = ai_generate_for_gate($pdo, $userId, 0);
    $handover['ai_paragraph'] = $ai;

    if (empty($handover['already'])) {
        $u = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
        $u->execute([$userId]);
        $email = (string)$u->fetchColumn();
        if ($email !== '') {
            $coach = $handover['coach_name'] ?? '';
            $code  = $handover['wyzai_code'] ?? '';
            // AI notes are a future, paid feature — omitted while disabled.
            $show   = ai_notes_enabled() && $ai !== null && $ai !== '';
            $aiHtml = $show ? '<p>' . nl2br(e($ai)) . '</p>' : '';
            $aiText = $show ? $ai . "\n\n" : '';
            $codeLine = ($code !== '' && strpos($code, 'PLACEHOLDER') === false)
                ? '<p>Your <strong>' . e($coach) . '</strong> code is <strong>' . e($code) . '</strong>.</p>' : '';
            $askHtml = '<p>Have a question? Open your toolkit and chat with <strong>WyzAI</strong> — the blue button in the bottom-right corner.</p>';
            $askText = "Have a question? Open your toolkit and chat with WyzAI (blue button, bottom-right).\n";
            $subject = 'You finished the DIY Creator Starter Toolkit';
            $html = $handover['summary_html'] . $aiHtml . $codeLine . $askHtml
                  . '<p><a href="' . e(APP['app_url']) . '/results/package.php">Open your full package</a></p>';
            $text = strip_tags($handover['summary_html']) . "\n\n" . $aiText . $askText
                  . 'Open your full package: ' . APP['app_url'] . "/results/package.php\n";
            mail_queue('package_complete', $email, $subject, $html, $text, $userId);
        }
    }
    return $handover;
}

/** Is a gate unlocked for a user? (small helper, no include of guard needed) */
function gate_unlocked_bool(PDO $pdo, int $userId, int $gate): bool
{
    $s = $pdo->prepare('SELECT unlocked_at FROM gate_progress WHERE user_id = ? AND gate_number = ? LIMIT 1');
    $s->execute([$userId, $gate]);
    $r = $s->fetch();
    return $r && $r['unlocked_at'] !== null;
}

/** Queue the gate-complete email with the coach note, unlocked PDFs, and code. */
function queue_gate_email(PDO $pdo, int $userId, int $gate, string $summaryHtml, ?string $aiParagraph, ?array $claim): void
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

    // AI notes are a future, paid feature — omitted while disabled.
    $ai = (ai_notes_enabled() && $aiParagraph !== null) ? trim($aiParagraph) : '';
    $aiHtml  = $ai !== '' ? '<p>' . nl2br(e($ai)) . '</p>' : '';
    $aiText  = $ai !== '' ? $ai . "\n\n" : '';

    $askHtml = '<p>Have a question? Open your toolkit and chat with <strong>WyzAI</strong> — the blue button in the bottom-right corner.</p>';
    $askText = "Have a question? Open your toolkit and chat with WyzAI (blue button, bottom-right).\n";

    $subject = 'You cleared ' . $label . ' in your DIY Creator Starter Toolkit';
    $html = '<div style="font-family:Inter,Arial,sans-serif;max-width:600px">'
          . $summaryHtml . $aiHtml . $codeLineHtml . $askHtml
          . '<p><a href="' . e(APP['app_url']) . '/dashboard.php">Back to your dashboard</a></p></div>';
    $text = strip_tags($summaryHtml) . "\n\n" . $aiText . $codeLine . $askText
          . 'Dashboard: ' . APP['app_url'] . "/dashboard.php\n";

    mail_queue('gate_' . $gate . '_complete', $email, $subject, $html, $text, $userId);
}
