<?php
/**
 * unsubscribe.php?e=<email>&t=<token>
 * Records an email opt-out. The token is a signed HMAC of the address, so the
 * link cannot be forged. Handles the one-click POST (List-Unsubscribe-Post) too.
 */

declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';

$email = strtolower(trim((string)($_GET['e'] ?? $_POST['e'] ?? '')));
$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');

$secrets = require CONFIG_DIR . '/secrets.php';
$expected = substr(hash_hmac('sha256', $email, $secrets['csrf_salt']), 0, 32);
$valid = $email !== '' && $token !== '' && hash_equals($expected, $token);

if ($valid) {
    try {
        db()->prepare('INSERT IGNORE INTO email_optouts (email, created_at) VALUES (?, ?)')
            ->execute([$email, now_dt()]);
    } catch (\Throwable $e) {
        error_log('unsubscribe insert failed: ' . $e->getMessage());
    }
}

// One-click POST just needs a 200.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    http_response_code(200);
    echo 'OK';
    exit;
}

$pageTitle = 'Unsubscribe';
$pageDesc  = 'Email preferences';
require __DIR__ . '/inc/head.php';
?>
<div class="wrap wrap--narrow auth">
  <div class="auth__card text-center">
    <?php if ($valid): ?>
      <h1>You are unsubscribed</h1>
      <p class="muted"><?= e($email) ?> will no longer receive non-essential emails from us. You will still get important account messages, like password resets.</p>
    <?php else: ?>
      <h1>Link not recognized</h1>
      <p class="muted">This unsubscribe link is not valid. If you want to stop emails, contact <a href="mailto:support@wyzcore.com">support@wyzcore.com</a> and we will sort it out.</p>
    <?php endif; ?>
    <p class="mt-lg"><a class="btn btn--ghost" href="https://www.wyzcore.com">Back to wyzcore.com</a></p>
  </div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
