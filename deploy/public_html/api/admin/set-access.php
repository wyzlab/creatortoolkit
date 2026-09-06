<?php
/**
 * POST /api/admin/set-access.php  {user_id, action:"revoke"|"restore"}  ->  {ok, status}
 * Admin only. Revoke blocks a learner from signing in (e.g. a universal-code
 * sign-up who turned out not to be a real buyer); restore lets them back in.
 * Their data is kept either way — only the account status changes.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/guard.php';

$admin = api_require_admin();
require_post();
csrf_check();

$in     = json_input();
$userId = (int)($in['user_id'] ?? 0);
$action = (string)($in['action'] ?? '');

if ($userId < 1 || !in_array($action, ['revoke', 'restore'], true)) {
    fail('Choose a user and an action (revoke or restore).');
}
if ($userId === (int)$admin['id']) {
    fail('You cannot revoke your own admin account here.');
}

$pdo = db();
$row = $pdo->prepare('SELECT id, role FROM users WHERE id = ? LIMIT 1');
$row->execute([$userId]);
$user = $row->fetch();
if (!$user) { fail('That account no longer exists.', 404); }
if (($user['role'] ?? '') === 'admin') { fail('Admin accounts cannot be revoked here.'); }

$status = $action === 'revoke' ? 'suspended' : 'active';
$pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, $userId]);

json_out(['ok' => true, 'status' => $status]);
