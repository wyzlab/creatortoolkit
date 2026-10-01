<?php
/**
 * confirm-signup.php — the link target from the email-confirmation message for a
 * universal (webinar) code. It validates the one-time token, records in the
 * session that this email + code is confirmed, and sends the person on to set a
 * password. The account is created only on the next step (set-password.php),
 * which re-checks the code under a lock.
 */

declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/guard.php';
require_once __DIR__ . '/inc/signup-verify.php';

if (is_logged_in()) {
    redirect('/dashboard.php');
}

$token = (string)($_GET['token'] ?? '');
$ok    = consume_signup_verification(db(), $token);

if ($ok !== null) {
    mark_signup_confirmed($ok['email'], $ok['code_id']);
    redirect('/set-password.php?confirmed=1');
}

// Invalid or expired link: show a friendly message, no account touched.
$pageTitle = 'Confirmation link expired';
$pageDesc  = 'That confirmation link is no longer valid.';
$bodyClass = 'page-auth';
require __DIR__ . '/inc/head.php';
?>
<div class="wrap wrap--narrow auth">
  <div class="auth__card">
    <h1 class="auth__title">That link has expired</h1>
    <p class="auth__lede">Confirmation links work once, within <?= SIGNUP_VERIFY_TTL_MIN ?> minutes.
       Enter your email and code again to get a fresh one.</p>
    <p class="text-center mt-lg"><a class="btn btn--primary" href="/index.php">Back to sign-in</a></p>
  </div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
