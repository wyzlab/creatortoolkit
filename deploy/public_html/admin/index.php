<?php
/**
 * admin/index.php — the admin console. Codes, buyers, and health at a glance.
 * Behind require_admin(). Everything loads through js/admin.js.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';

require_admin();

$pageTitle = 'Admin';
$pageDesc  = 'Manage access codes and see how buyers are moving through the toolkit.';
$pageScripts = ['/js/admin.js'];
require __DIR__ . '/../inc/head.php';
?>
<div class="wrap" data-admin>
  <div class="tool-shell__head">
    <span class="badge badge--open">Admin</span>
    <h1>Console</h1>
    <p class="muted">Create access codes, hand them to buyers, and see where people are in the journey.</p>
  </div>

  <!-- Overview -->
  <section class="card admin-section">
    <h2>Overview</h2>
    <div class="stat-grid" data-stats>
      <div class="stat"><span class="stat__num">&hellip;</span><span class="stat__label">Buyers</span></div>
    </div>
    <div class="scroll-x">
      <table class="admin-table" data-dropoff hidden>
        <thead><tr><th>Tool</th><th>Gate</th><th>Started</th><th>Completed</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </section>

  <!-- Generate a batch of codes -->
  <section class="card admin-section">
    <h2>Create access codes</h2>
    <p class="muted">Make a batch of codes to hand out yourself. They are shown once here, so copy them.</p>
    <form data-form="gen" class="admin-form">
      <div class="field">
        <label class="field__label" for="gen-count">How many codes</label>
        <input class="input" id="gen-count" name="count" type="number" min="1" max="500" value="20">
      </div>
      <div class="field">
        <label class="field__label" for="gen-batch">Batch name (optional)</label>
        <input class="input" id="gen-batch" name="batch_label" type="text" placeholder="e.g. launch-oct">
      </div>
      <button class="btn btn--primary" type="submit">Generate codes</button>
    </form>
    <div class="notice" data-gen-notice hidden></div>
    <div data-gen-result hidden>
      <div class="admin-result-head">
        <strong data-gen-count></strong>
        <button class="btn btn--sm btn--ghost" type="button" data-copy="gen">Copy all</button>
      </div>
      <textarea class="textarea" data-gen-codes readonly rows="8"></textarea>
    </div>
  </section>

  <!-- Issue codes to a list of buyers -->
  <section class="card admin-section">
    <h2>Issue codes to buyers</h2>
    <p class="muted">Paste buyer emails, one per line. Each gets their own code, and an email with a claim link. No copy-paste per person.</p>
    <form data-form="issue" class="admin-form">
      <div class="field">
        <label class="field__label" for="issue-emails">Buyer emails</label>
        <textarea class="textarea" id="issue-emails" name="emails" rows="6" placeholder="maria@example.com&#10;jose@example.com"></textarea>
      </div>
      <div class="field">
        <label class="field__label" for="issue-batch">Batch name (optional)</label>
        <input class="input" id="issue-batch" name="batch_label" type="text" placeholder="e.g. purchases">
      </div>
      <button class="btn btn--cta" type="submit">Issue and email codes</button>
    </form>
    <div class="notice" data-issue-notice hidden></div>
    <div class="scroll-x">
      <table class="admin-table" data-issue-result hidden>
        <thead><tr><th>Email</th><th>Code</th><th>Status</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </section>

  <!-- Recent codes -->
  <section class="card admin-section">
    <h2>Recent codes</h2>
    <div class="scroll-x">
      <table class="admin-table" data-codes>
        <thead><tr><th>Code</th><th>Batch</th><th>Issued to</th><th>Status</th><th>Claimed by</th></tr></thead>
        <tbody><tr><td colspan="5" class="muted">Loading&hellip;</td></tr></tbody>
      </table>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../inc/footer.php'; ?>
