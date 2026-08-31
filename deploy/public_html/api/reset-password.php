<?php
/**
 * POST /api/reset-password.php  {token, password}  ->  {ok, redirect}
 * Token single use, 60 minute expiry. Minimum 10 character password.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';

require_post();
csrf_check();

$in       = json_input();
$token    = (string)($in['token'] ?? '');
$password = (string)($in['password'] ?? '');

rate_limit_guard('reset-password', '', 10);

if ($token === '') {
    fail('That reset link is not valid. Please request a new one.', 400);
}
if (strlen($password) < 10) {
    fail('Please choose a password of at least 10 characters.');
}

$secrets = require CONFIG_DIR . '/secrets.php';
$tokenHash = hash_hmac('sha256', $token, $secrets['csrf_salt']);

$pdo = db();
try {
    $pdo->beginTransaction();

    $sel = $pdo->prepare(
        'SELECT id, user_id, expires_at, used_at
           FROM password_resets WHERE token_hash = ? FOR UPDATE'
    );
    $sel->execute([$tokenHash]);
    $row = $sel->fetch();

    if (!$row || $row['used_at'] !== null || $row['expires_at'] < now_dt()) {
        $pdo->rollBack();
        fail('That reset link has expired or was already used. Please request a new one.', 400);
    }

    $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $upd->execute([password_hash($password, PASSWORD_DEFAULT), (int)$row['user_id']]);

    $use = $pdo->prepare('UPDATE password_resets SET used_at = ? WHERE id = ?');
    $use->execute([now_dt(), (int)$row['id']]);

    // Invalidate any other outstanding tokens for this user.
    $clear = $pdo->prepare(
        'UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL'
    );
    $clear->execute([now_dt(), (int)$row['user_id']]);

    $pdo->commit();
} catch (\Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('reset-password failed: ' . $ex->getMessage());
    fail('We could not reset your password just now. Please try again.', 500);
}

json_out(['ok' => true, 'redirect' => '/index.php?reset=1']);
