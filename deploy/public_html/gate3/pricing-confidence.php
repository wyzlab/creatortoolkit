<?php
/**
 * gate3/pricing-confidence.php
 * Server-side guard first: a locked gate returns a redirect, not a page.
 * The interactive tool mounts here from Stage B (js/tools/pricing-confidence.js).
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';
require_gate(3);

$pageTitle = 'Pricing Confidence Starter';
require __DIR__ . '/../inc/head.php';
?>
<div class="wrap" data-tool-root data-tool-slug="pricing-confidence" data-tool-gate="3">
  <div class="tool-shell">
    <div class="tool-shell__head">
      <span class="badge badge--studio">Studio Original</span>
      <h1>Pricing Confidence Starter</h1>
    </div>
    <div class="tool-step">
      <p>This tool becomes interactive in the next release. Your gate access is already working.</p>
      <p class="mt-lg"><a class="btn btn--ghost" href="/dashboard.php">Back to dashboard</a></p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../inc/footer.php'; ?>
