<?php
/**
 * index.php — the entry page. Claim a toolkit with an access code, or log in.
 */

declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/guard.php';

// Already signed in? Go straight to the dashboard.
if (is_logged_in()) {
    redirect('/dashboard.php');
}

$pageTitle = 'Welcome';
$pageDesc  = 'Claim your DIY Creator Starter Toolkit, or log in to continue.';
$bodyClass = 'page-auth';
$pageScripts = ['/js/auth.js'];

$justReset = isset($_GET['reset']);

require __DIR__ . '/inc/head.php';
?>
<div class="wrap wrap--narrow auth" data-auth="index">
  <div class="auth__card">
    <h1 class="auth__title">Your creator toolkit</h1>
    <p class="auth__lede">Ten tools, three gates, one connected path. Start where the work carries forward.</p>

    <div class="notice" data-notice hidden></div>
    <?php if ($justReset): ?>
      <div class="notice notice--success">Your password is set. Log in below.</div>
    <?php endif; ?>

    <div class="auth__tabs" role="tablist">
      <button class="auth__tab" data-tab="claim" role="tab" aria-selected="true">I have a code</button>
      <button class="auth__tab" data-tab="login" role="tab" aria-selected="false">Log in</button>
    </div>

    <!-- Claim panel -->
    <div data-panel="claim">
      <form data-form="claim" novalidate>
        <div class="field">
          <label class="field__label" for="claim-email">Your email</label>
          <input class="input" id="claim-email" name="email" type="email" autocomplete="email" required>
        </div>
        <div class="field">
          <label class="field__label" for="claim-code">Access code</label>
          <input class="input" id="claim-code" name="code" type="text" autocomplete="off"
                 placeholder="e.g. 0-H3G-QKS" required>
          <span class="field__hint">Spaces and hyphens do not matter. We match either way.</span>
        </div>

        <!-- Revealed after the code checks out -->
        <div class="field" data-step="password" hidden>
          <label class="field__label" for="claim-newpw">Choose a password</label>
          <div class="pw-field">
            <input class="input" id="claim-newpw" name="password" type="password"
                   data-newpw autocomplete="new-password" minlength="10">
            <button type="button" class="pw-toggle" data-pw-toggle aria-controls="claim-newpw" aria-pressed="false" aria-label="Show password">Show</button>
          </div>
          <span class="field__hint">At least 10 characters. That is the only rule.</span>
        </div>

        <button class="btn btn--primary btn--block" type="submit">Check my code</button>
      </form>
    </div>

    <!-- Login panel -->
    <div data-panel="login" hidden>
      <form data-form="login" novalidate>
        <div class="field">
          <label class="field__label" for="login-email">Email</label>
          <input class="input" id="login-email" name="email" type="email" autocomplete="email" required>
        </div>
        <div class="field">
          <label class="field__label" for="login-pw">Password</label>
          <div class="pw-field">
            <input class="input" id="login-pw" name="password" type="password" autocomplete="current-password" required>
            <button type="button" class="pw-toggle" data-pw-toggle aria-controls="login-pw" aria-pressed="false" aria-label="Show password">Show</button>
          </div>
        </div>
        <button class="btn btn--primary btn--block" type="submit">Log in</button>
        <p class="text-center mt-lg">
          <a href="#" data-action="forgot">Forgot your password?</a>
        </p>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
