<?php
/**
 * POST /api/login.php  {email, password}  ->  {ok, redirect}
 *
 * Rate limited. Regenerates the session id. Rehashes the password when the
 * algorithm parameters have moved on. Generic error for a bad email or a bad
 * password, so neither can be probed.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';

require_post();
csrf_check();

$in       = json_input();
$email    = normalize_email((string)($in['email'] ?? ''));
$password = (string)($in['password'] ?? '');

rate_limit_guard('login', $email, 5);

$generic = 'That email and password did not match. Please try again.';

if (!is_email($email) || $password === '') {
    fail($generic, 401);
}

$stmt = db()->prepare(
    'SELECT id, password_hash, status FROM users WHERE email = ? LIMIT 1'
);
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || $user['password_hash'] === null || $user['status'] !== 'active') {
    fail($generic, 401);
}

if (!password_verify($password, $user['password_hash'])) {
    fail($generic, 401);
}

// Successful auth: rehash if needed, refresh the session, clear the limiter.
if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
    $rehash = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $rehash->execute([password_hash($password, PASSWORD_DEFAULT), (int)$user['id']]);
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];

$upd = db()->prepare('UPDATE users SET last_login_at = ? WHERE id = ?');
$upd->execute([now_dt(), (int)$user['id']]);

rate_limit_clear(rl_identifier('email:' . $email), 'login');
rate_limit_clear(rl_identifier('ip:' . client_ip()), 'login');

json_out(['ok' => true, 'redirect' => '/dashboard.php']);
