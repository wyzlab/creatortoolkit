<?php
/**
 * claim.php — the post-checkout thank-you page. A buyer who just purchased the
 * DIY Creator Toolkit on wyzcore.com lands here and sets a password to open the
 * toolkit. If the checkout redirect carries ?email=..., the page claims
 * automatically (no typing); otherwise the buyer enters the email they paid
 * with. Access itself is granted only by the purchase webhook (which checks the
 * product), never by this page — so an email that did not buy is sent to the
 * purchase link instead.
 */

declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/guard.php';

// Already signed in? Straight to the dashboard.
if (is_logged_in()) {
    redirect('/dashboard.php');
}

// The checkout can redirect with the buyer's email attached
// (…/claim.php?email={{buyer_email}}) so the page claims with no typing.
$prefillEmail = normalize_email((string)($_GET['email'] ?? ''));
if (!is_email($prefillEmail)) { $prefillEmail = ''; }
$purchaseUrl = trim((string)(APP['purchase_url'] ?? ''));

$pageTitle = 'Thank you for your purchase';
$pageDesc  = 'Claim access to your DIY Creator Starter Toolkit.';
$bodyClass = 'page-auth';

require __DIR__ . '/inc/head.php';
?>
<div class="wrap wrap--narrow auth">
  <div class="auth__card" data-claim<?= $prefillEmail !== '' ? ' data-claim-auto="' . e($prefillEmail) . '"' : '' ?><?= $purchaseUrl !== '' ? ' data-purchase-url="' . e($purchaseUrl) . '"' : '' ?>>
    <span class="badge badge--done" style="display:block;width:max-content;margin:0 auto var(--space-md)">Purchase complete</span>
    <h1 class="auth__title">Thank you for your purchase</h1>

    <div class="notice" data-claim-notice hidden></div>

    <!-- Step 1: confirm the purchase email -->
    <div data-claim-step="email">
      <p class="auth__lede">Enter the email you used at checkout to open your toolkit.</p>
      <form data-form="claim-check" novalidate>
        <div class="field">
          <label class="field__label" for="claim-email">Your purchase email</label>
          <input class="input" id="claim-email" name="email" type="email" autocomplete="email" value="<?= e($prefillEmail) ?>" required>
        </div>
        <button class="btn btn--cta btn--block" type="submit">Claim access</button>
      </form>
      <p class="text-center mt-lg muted">Already set your password? <a href="/index.php">Log in</a>.</p>
    </div>

    <!-- No matching purchase: invite them to buy the toolkit -->
    <div data-claim-step="nobuy" hidden>
      <p class="auth__lede">We couldn't find a DIY Creator Toolkit purchase for that email. If you used a different email, try again — or get the toolkit below.</p>
      <?php if ($purchaseUrl !== ''): ?>
        <a class="btn btn--cta btn--block" href="<?= e($purchaseUrl) ?>">Get the DIY Creator Toolkit</a>
      <?php endif; ?>
      <p class="text-center mt-lg"><button type="button" class="btn btn--ghost btn--sm" data-claim-retry>Try another email</button></p>
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
