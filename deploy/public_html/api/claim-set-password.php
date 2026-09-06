<?php
/**
 * POST /api/claim-set-password.php  {password}  ->  {ok, redirect}
 *
 * Step 2 of the on-page claim. Sets the password for the account that
 * claim-check.php authorized in THIS session, then logs them straight in.
 * Guards: the session authorization must exist and be unexpired, and the
 * account must still be active with no password yet (so a link/other tab that
 * already set it wins and this cannot overwrite it).
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';

require_post();
csrf_check();

$uid = (int)($_SESSION['claim_uid'] ?? 0);
$exp = (int)($_SESSION['claim_exp'] ?? 0);
if ($uid < 1 || $exp < time()) {
    unset($_SESSION['claim_uid'], $_SESSION['claim_email'], $_SESSION['claim_exp']);
    fail('Your claim session expired. Please enter your email again.', 440);
}

rate_limit_guard('claim-set-password', (string)($_SESSION['claim_email'] ?? ''), 8);

$in       = json_input();
$password = (string)($in['password'] ?? '');
if (strlen($password) < 10) {
    fail('Please choose a password of at least 10 characters.');
}

$pdo = db();
try {
    $pdo->beginTransaction();
    // Re-check under lock: still active and NOT already set (first claim wins).
    $sel = $pdo->prepare('SELECT id FROM users WHERE id = ? AND status = "active" AND password_hash IS NULL FOR UPDATE');
    $sel->execute([$uid]);
    if (!$sel->fetch()) {
        $pdo->rollBack();
        unset($_SESSION['claim_uid'], $_SESSION['claim_email'], $_SESSION['claim_exp']);
        fail('This account is already set up. Please log in instead.', 409);
    }
    $pdo->prepare('UPDATE users SET password_hash = ?, last_login_at = ? WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_DEFAULT), now_dt(), $uid]);
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('claim-set-password failed: ' . $e->getMessage());
    fail('We could not set your password just now. Please try again.', 500);
}

// Log them straight in.
unset($_SESSION['claim_uid'], $_SESSION['claim_email'], $_SESSION['claim_exp']);
session_regenerate_id(true);
$_SESSION['user_id'] = $uid;

json_out(['ok' => true, 'redirect' => '/dashboard.php']);
