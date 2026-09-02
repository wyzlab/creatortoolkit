<?php
/**
 * POST /api/complete-tool.php  {tool_slug, answers, profile_version}
 *   -> { ok, result:{json,html}, pdf_unlocked, gate_complete,
 *        wyzai_code?, coach_name?, next_gate_unlocked? }
 *
 * Validates required fields, writes answers into the shared profile (with
 * stale propagation), builds the result, unlocks the PDF, updates the gate
 * counter, and closes the gate when its last tool finishes. One transaction.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';
require_once __DIR__ . '/../inc/toolengine.php';
require_once __DIR__ . '/../inc/results.php';
require_once __DIR__ . '/../inc/gates.php';

$user = api_require_login();
$uid  = (int)$user['id'];

require_post();
csrf_check();

$in      = json_input();
$slug    = (string)($in['tool_slug'] ?? '');
$answers = is_array($in['answers'] ?? null) ? $in['answers'] : [];

$def = tool_def($slug);
$reg = tool($slug);
if (!$def || !$reg) fail('Unknown tool.', 404);
$gate = (int)$def['gate'];
api_require_gate($uid, $gate);

// Server-side required-field validation. Scores never block; required fields do.
$missing = [];
foreach (td_required_keys($def) as $key) {
    if (value_empty($answers[$key] ?? null)) { $missing[] = $key; }
}
if ($missing) {
    fail('Please fill the required fields before finishing.', 422, ['missing' => $missing]);
}

$pdo = db();
$nowStr = now_dt();

try {
    $pdo->beginTransaction();

    // Lock and load the profile (row lock prevents lost updates across tools).
    $pf = $pdo->prepare('SELECT id, profile_json, version FROM user_profile WHERE user_id = ? FOR UPDATE');
    $pf->execute([$uid]);
    $prow = $pf->fetch();
    $profile = $prow ? (json_decode((string)$prow['profile_json'], true) ?: []) : [];

    // Write this tool's answers into the profile, propagate staleness, clear own.
    apply_writes_to_profile($profile, $slug, $def, $answers);
    derive_profile($profile, $slug, $answers);   // e.g. sparker modules -> one block
    clear_stale_for($profile, $slug, $answers);
    $newVersion = ($prow ? (int)$prow['version'] : 1) + 1;

    if ($prow) {
        $pdo->prepare('UPDATE user_profile SET profile_json = ?, version = ?, updated_at = ? WHERE user_id = ?')
            ->execute([json_encode($profile), $newVersion, $nowStr, $uid]);
    } else {
        $pdo->prepare('INSERT INTO user_profile (user_id, profile_json, version, updated_at) VALUES (?, ?, ?, ?)')
            ->execute([$uid, json_encode($profile), $newVersion, $nowStr]);
    }

    // Upsert the tool session as completed.
    $pdo->prepare(
        'INSERT INTO tool_sessions (user_id, gate_number, tool_slug, current_step, answers_json, status, started_at, updated_at, completed_at)
         VALUES (?, ?, ?, ?, ?, "completed", ?, ?, ?)
         ON DUPLICATE KEY UPDATE answers_json = VALUES(answers_json), status = "completed",
                                 updated_at = VALUES(updated_at),
                                 completed_at = COALESCE(completed_at, VALUES(completed_at))'
    )->execute([$uid, $gate, $slug, count($def['steps']), json_encode($answers), $nowStr, $nowStr, $nowStr]);

    // Session id for the result FK.
    $sid = (int)$pdo->query('SELECT id FROM tool_sessions WHERE user_id = ' . $uid
         . " AND tool_slug = " . $pdo->quote($slug) . ' LIMIT 1')->fetchColumn();

    // Build and store the result (replace any prior result for this tool).
    [$resJson, $resHtml] = build_tool_result($slug, $answers, $profile);
    $pdo->prepare('DELETE FROM tool_results WHERE user_id = ? AND tool_slug = ?')->execute([$uid, $slug]);
    $pdo->prepare('INSERT INTO tool_results (session_id, user_id, tool_slug, result_json, result_html, created_at)
                   VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$sid, $uid, $slug, json_encode($resJson), $resHtml, $nowStr]);

    // Unlock the PDF for this tool.
    $pdo->prepare('INSERT INTO pdf_unlocks (user_id, tool_slug, unlocked_at)
                   VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE unlocked_at = unlocked_at')
        ->execute([$uid, $slug, $nowStr]);

    // Update the gate counter, and close the gate if this was the last tool.
    [$done, $need] = recount_gate($pdo, $uid, $gate);
    $gateComplete = false;
    $handover = null;
    if ($done >= $need) {
        $handover = close_gate($pdo, $uid, $gate);
        $gateComplete = $handover !== null;
    }

    $pdo->commit();
} catch (\Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('complete-tool failed: ' . $ex->getMessage());
    fail('We could not save your result just now. Your answers are still saved. Please try again.', 500);
}

$out = [
    'ok' => true,
    'result' => ['json' => $resJson, 'html' => $resHtml],
    'pdf_unlocked' => true,
    'gate_complete' => $gateComplete,
];
if ($gateComplete && $handover) {
    $out['wyzai_code']         = $handover['wyzai_code'];
    $out['coach_name']         = $handover['coach_name'];
    $out['next_gate_unlocked'] = $handover['next_gate_unlocked'];
}
json_out($out);
