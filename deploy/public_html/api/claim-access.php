<?php
/**
 * POST /api/claim-access.php  {email}  ->  {ok, message}
 *
 * The thank-you page's "Claim access" button. It NEVER creates access on its
 * own — access is granted only by the purchase webhook (which checks the
 * product). This endpoint just (re)sends the set-password link to an email that
 * a matching purchase already provisioned. The response is uniform whether or
 * not the email matches, so it cannot be used to discover who bought.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/provision.php';

require_post();
csrf_check();

$in    = json_input();
$email = normalize_email((string)($in['email'] ?? ''));

rate_limit_guard('claim-access', $email, 5);

$uniform = ['ok' => true, 'message' =>
    'If a DIY Creator Toolkit purchase matches that email, we have sent a link to set up '
    . 'or open your account. Please check your inbox (and spam) in a minute.'];

if (!is_email($email)) {
    // Same generic reply; do not reveal that the email was malformed vs unknown.
    json_out($uniform);
}

$pdo = db();
$u = $pdo->prepare('SELECT id, password_hash, status FROM users WHERE email = ? LIMIT 1');
$u->execute([$email]);
$row = $u->fetch();

// Only act for a provisioned, active account that has not set a password yet.
// (Already-set-up or revoked accounts simply get the uniform message.)
if ($row && $row['status'] === 'active' && $row['password_hash'] === null) {
    try {
        $link = make_set_password_link($pdo, (int)$row['id']);
        send_setpw_email($pdo, $email, $link, (int)$row['id']);
    } catch (\Throwable $e) {
        error_log('claim-access resend failed: ' . $e->getMessage());
        // Still return the uniform message; nothing to leak.
    }
}

json_out($uniform);
