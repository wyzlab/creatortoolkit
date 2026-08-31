<?php
/**
 * POST /api/save-progress.php  {tool_slug, step, answers, profile_version}
 *   -> { ok, saved_at, profile_version }
 * Autosave. Fires on a 2 second debounce after any change, and on blur.
 * Stores answers in tool_sessions; clears any stale flags the learner just
 * resolved by filling a field.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';
require_once __DIR__ . '/../inc/toolengine.php';

$user = api_require_login();
$uid  = (int)$user['id'];

require_post();
csrf_check();

$in    = json_input();
$slug  = (string)($in['tool_slug'] ?? '');
$step  = max(1, (int)($in['step'] ?? 1));
$answers = is_array($in['answers'] ?? null) ? $in['answers'] : [];

$def = tool_def($slug);
$reg = tool($slug);
if (!$def || !$reg) fail('Unknown tool.', 404);
api_require_gate($uid, (int)$def['gate']);

$pdo = db();
$nowStr = now_dt();

// Upsert the tool session (keep any existing completed status).
$stmt = $pdo->prepare(
    'INSERT INTO tool_sessions (user_id, gate_number, tool_slug, current_step, answers_json, status, started_at, updated_at)
     VALUES (?, ?, ?, ?, ?, "in_progress", ?, ?)
     ON DUPLICATE KEY UPDATE current_step = VALUES(current_step),
                             answers_json = VALUES(answers_json),
                             updated_at = VALUES(updated_at)'
);
$stmt->execute([$uid, (int)$def['gate'], $slug, $step, json_encode($answers), $nowStr, $nowStr]);

// Clear stale flags for any field the learner just filled in.
$version = 1;
$pf = $pdo->prepare('SELECT profile_json, version FROM user_profile WHERE user_id = ? FOR UPDATE');
// FOR UPDATE only valid inside a transaction; wrap a tiny one.
$pdo->beginTransaction();
try {
    $pf->execute([$uid]);
    $prow = $pf->fetch();
    if ($prow) {
        $profile = json_decode((string)$prow['profile_json'], true) ?: [];
        $version = (int)$prow['version'];
        if (!empty($profile['_stale'])) {
            $before = json_encode($profile['_stale']);
            clear_stale_for($profile, $slug, $answers);
            if (json_encode($profile['_stale'] ?? []) !== $before) {
                $version++;
                $pdo->prepare('UPDATE user_profile SET profile_json = ?, version = ?, updated_at = ? WHERE user_id = ?')
                    ->execute([json_encode($profile), $version, $nowStr, $uid]);
            }
        }
    }
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('save-progress stale-clear failed: ' . $e->getMessage());
}

json_out(['ok' => true, 'saved_at' => $nowStr, 'profile_version' => $version]);
