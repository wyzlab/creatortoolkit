<?php
/**
 * claim.php — the post-checkout thank-you page. A buyer who just purchased the
 * DIY Creator Toolkit on wyzcore.com lands here, enters the email they used,
 * and clicks "Claim access". That (re)sends their set-password link.
 *
 * Access itself is granted by the purchase webhook (which checks the product),
 * never by this page — so entering an email that did not buy does nothing.
 */

declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/guard.php';

// Already signed in? Straight to the dashboard.
if (is_logged_in()) {
    redirect('/dashboard.php');
}

$pageTitle = 'Thank you for your purchase';
$pageDesc  = 'Claim access to your DIY Creator Starter Toolkit.';
$bodyClass = 'page-auth';

require __DIR__ . '/inc/head.php';
?>
<div class="wrap wrap--narrow auth">
  <div class="auth__card" data-claim>
    <span class="badge badge--done" style="display:block;width:max-content;margin:0 auto var(--space-md)">Purchase complete</span>
    <h1 class="auth__title">Thank you for your purchase</h1>

    <div class="notice" data-claim-notice hidden></div>

    <!-- Step 1: confirm the purchase email -->
    <div data-claim-step="email">
      <p class="auth__lede">Enter the email you used at checkout to open your toolkit.</p>
      <form data-form="claim-check" novalidate>
        <div class="field">
          <label class="field__label" for="claim-email">Your purchase email</label>
          <input class="input" id="claim-email" name="email" type="email" autocomplete="email" required>
        </div>
        <button class="btn btn--cta btn--block" type="submit">Claim access</button>
      </form>
      <p class="text-center mt-lg muted">Already set your password? <a href="/index.php">Log in</a>.</p>
    </div>

    <!-- Step 2: set a password, then straight into the toolkit -->
    <div data-claim-step="password" hidden>
      <p class="auth__lede">You're in! Choose a password for <strong data-claim-email></strong> and we'll take you straight to your toolkit.</p>
      <form data-form="claim-set-password" novalidate>
        <div class="field">
          <label class="field__label" for="claim-newpw">Choose a password</label>
          <div class="pw-field">
            <input class="input" id="claim-newpw" name="password" type="password"
                   autocomplete="new-password" minlength="10" required>
            <button type="button" class="pw-toggle" data-pw-toggle aria-controls="claim-newpw" aria-pressed="false" aria-label="Show password">Show</button>
          </div>
          <span class="field__hint">At least 10 characters. That is the only rule.</span>
        </div>
        <button class="btn btn--cta btn--block" type="submit">Set password &amp; open toolkit</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
