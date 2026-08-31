<?php
/**
 * reset.php — choose a new password from a single-use reset token.
 */

declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/guard.php';

$token = isset($_GET['token']) ? (string)$_GET['token'] : '';

$pageTitle = 'Reset your password';
$pageDesc  = 'Choose a new password for your toolkit.';
$bodyClass = 'page-auth';
$pageScripts = ['/js/auth.js'];

require __DIR__ . '/inc/head.php';
?>
<div class="wrap wrap--narrow auth" data-auth="reset">
  <div class="auth__card">
    <h1 class="auth__title">Choose a new password</h1>

    <?php if ($token === ''): ?>
      <div class="notice notice--error">This reset link is missing its token. Please request a new one from the login page.</div>
      <p class="text-center mt-lg"><a href="/index.php">Back to login</a></p>
    <?php else: ?>
      <p class="auth__lede">Enter a new password. This link works once, within 60 minutes.</p>
      <div class="notice" data-notice hidden></div>
      <form data-form="reset" novalidate>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field">
          <label class="field__label" for="reset-pw">New password</label>
          <input class="input" id="reset-pw" name="password" type="password"
                 autocomplete="new-password" minlength="10" required>
          <span class="field__hint">At least 10 characters.</span>
        </div>
        <button class="btn btn--primary btn--block" type="submit">Save new password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
