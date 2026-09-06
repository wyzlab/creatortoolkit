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
  <div class="auth__card">
    <span class="badge badge--done" style="display:block;width:max-content;margin:0 auto var(--space-md)">Purchase complete</span>
    <h1 class="auth__title">Thank you for your purchase</h1>
    <p class="auth__lede">Enter the email you used at checkout and we will send you a link to set your password and open your toolkit.</p>

    <div class="notice" data-claim-notice hidden></div>

    <form data-form="claim-access" novalidate>
      <div class="field">
        <label class="field__label" for="claim-access-email">Your purchase email</label>
        <input class="input" id="claim-access-email" name="email" type="email" autocomplete="email" required>
      </div>
      <button class="btn btn--cta btn--block" type="submit">Claim access</button>
    </form>

    <p class="text-center mt-lg muted">
      Already set your password? <a href="/index.php">Log in</a>.
    </p>
  </div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
