<?php
/**
 * POST /api/save-journey-title.php  {title}  ->  {ok, title}
 * Saves the learner's editable build title (shown above the gates on the
 * dashboard). Stored in the shared profile.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';

$user = api_require_login();
$uid  = (int)$user['id'];

require_post();
csrf_check();

$in    = json_input();
$title = trim((string)($in['title'] ?? ''));
if ($title === '') { $title = 'Offer 1'; }
$title = mb_substr($title, 0, 120);

$pdo = db();
$nowStr = now_dt();
try {
    $pdo->beginTransaction();
    $pf = $pdo->prepare('SELECT profile_json, version FROM user_profile WHERE user_id = ? FOR UPDATE');
    $pf->execute([$uid]);
    $row = $pf->fetch();
    $profile = $row ? (json_decode((string)$row['profile_json'], true) ?: []) : [];
    $profile['journey_title'] = $title;
    if ($row) {
        $pdo->prepare('UPDATE user_profile SET profile_json = ?, version = version + 1, updated_at = ? WHERE user_id = ?')
            ->execute([json_encode($profile), $nowStr, $uid]);
    } else {
        $pdo->prepare('INSERT INTO user_profile (user_id, profile_json, version, updated_at) VALUES (?, ?, 1, ?)')
            ->execute([$uid, json_encode($profile), $nowStr]);
    }
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fail('We could not save that name just now. Please try again.', 500);
}

json_out(['ok' => true, 'title' => $title]);
