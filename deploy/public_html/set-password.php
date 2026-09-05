<?php
/**
 * set-password.php — standalone page to set a password against an access
 * code, in one step. The claim tab on index.php does the same inline; this
 * page gives buyers a direct link if you prefer to send one.
 */

declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/guard.php';

if (is_logged_in()) {
    redirect('/dashboard.php');
}

$pageTitle = 'Set your password';
$pageDesc  = 'Set up your DIY Creator Starter Toolkit.';
$bodyClass = 'page-auth';
$pageScripts = ['/js/auth.js'];

// Optional prefill from a link, e.g. /set-password.php?email=...
$prefillEmail = isset($_GET['email']) ? normalize_email((string)$_GET['email']) : '';

require __DIR__ . '/inc/head.php';
?>
<div class="wrap wrap--narrow auth" data-auth="setpw">
  <div class="auth__card">
    <h1 class="auth__title">Set up your toolkit</h1>
    <p class="auth__lede">Enter your email and access code, then choose a password.</p>

    <div class="notice" data-notice hidden></div>

    <form data-form="setpw-standalone" novalidate>
      <div class="field">
        <label class="field__label" for="sp-email">Your email</label>
        <input class="input" id="sp-email" name="email" type="email" autocomplete="email"
               value="<?= e($prefillEmail) ?>" required>
      </div>
      <div class="field">
        <label class="field__label" for="sp-code">Access code</label>
        <input class="input" id="sp-code" name="code" type="text" autocomplete="off"
               placeholder="e.g. 0-H3G-QKS" required>
        <span class="field__hint">Spaces and hyphens do not matter.</span>
      </div>
      <div class="field">
        <label class="field__label" for="sp-pw">Choose a password</label>
        <div class="pw-field">
          <input class="input" id="sp-pw" name="password" type="password"
                 autocomplete="new-password" minlength="10" required>
          <button type="button" class="pw-toggle" data-pw-toggle aria-controls="sp-pw" aria-pressed="false" aria-label="Show password">Show</button>
        </div>
        <span class="field__hint">At least 10 characters. That is the only rule.</span>
      </div>
      <button class="btn btn--primary btn--block" type="submit">Set password and start</button>
    </form>

    <p class="text-center mt-lg"><a href="/index.php">Already set up? Log in.</a></p>
  </div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
