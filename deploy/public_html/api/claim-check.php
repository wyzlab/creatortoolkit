<?php
/**
 * POST /api/claim-check.php  {email}  ->  {ok, ready, message}
 *
 * Step 1 of the on-page claim. If a matching purchase already provisioned this
 * email (an active account that has not set a password yet), authorize THIS
 * browser session to set the password inline (step 2), and return ready:true.
 * Access itself is only ever created by the purchase webhook, never here.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';

require_post();
csrf_check();

$in    = json_input();
$email = normalize_email((string)($in['email'] ?? ''));

rate_limit_guard('claim-check', $email, 8);

if (!is_email($email)) {
    json_out(['ok' => true, 'ready' => false,
        'message' => 'Please enter the email address you used at checkout.']);
}

$pdo = db();
$u = $pdo->prepare('SELECT id, password_hash, status FROM users WHERE email = ? LIMIT 1');
$u->execute([$email]);
$row = $u->fetch();

// A real, provisioned buyer who has not set a password yet: let them set it now.
if ($row && $row['status'] === 'active' && $row['password_hash'] === null) {
    $_SESSION['claim_uid']   = (int)$row['id'];
    $_SESSION['claim_email'] = $email;
    $_SESSION['claim_exp']   = time() + 900;   // 15 minutes to finish
    json_out(['ok' => true, 'ready' => true, 'email' => $email]);
}

// Already set up.
if ($row && $row['password_hash'] !== null) {
    json_out(['ok' => true, 'ready' => false, 'already' => true,
        'message' => 'You already set your password for this email. Please log in.']);
}

// Not found / not (yet) provisioned. Keep it gentle; do not confirm a purchase.
json_out(['ok' => true, 'ready' => false,
    'message' => 'We could not find your toolkit purchase for that email yet. If you just bought, wait a moment and try again, or use the exact email you paid with.']);
