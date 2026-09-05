<?php
/**
 * offers/index.php — "My Offers". Every One-Page Offer a learner finishes is
 * saved here: open one to print it to PDF, or start another.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';
require_once __DIR__ . '/../inc/offers.php';

require_login();
$user = current_user();
$uid  = (int)$user['id'];

$offers = list_offers(db(), $uid);

// Can they build an offer yet? (Gate 2 unlocked — the One-Page Offer lives there.)
$g2 = db()->prepare('SELECT unlocked_at FROM gate_progress WHERE user_id = ? AND gate_number = 2 LIMIT 1');
$g2->execute([$uid]);
$g2row = $g2->fetch();
$gate2Unlocked = $g2row && $g2row['unlocked_at'] !== null;

$pageTitle = 'My Offers';
require __DIR__ . '/../inc/head.php';
?>
<div class="wrap wrap--narrow">
  <div class="tool-shell">
    <div class="tool-shell__head"><h1>My Offers</h1></div>
    <div class="tool-step">
      <p>Every offer you build is saved here. Open one to print it as a PDF, or start a new offer.</p>

      <?php if ($gate2Unlocked): ?>
        <p><a class="btn btn--cta" href="/gate2/one-page-offer.php?again=1">Create a new offer</a></p>
      <?php else: ?>
        <p class="notice">Finish Gate 1, then build your first offer in Gate 2. Your offers will appear here.</p>
      <?php endif; ?>

      <?php if ($offers): ?>
        <ul class="tool-list">
          <?php foreach ($offers as $o): ?>
          <li class="tool-row offer-row">
            <div class="tool-row__main">
              <span class="tool-row__name"><?= e($o['title']) ?></span>
              <span class="offer-row__meta"><?= e(date('M j, Y', strtotime((string)$o['created_at']))) ?></span>
            </div>
            <span class="offer-row__actions">
              <a class="btn btn--sm btn--primary" href="/offers/view.php?id=<?= (int)$o['id'] ?>">View &amp; print</a>
              <form method="post" action="/api/delete-offer.php" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                <button type="submit" class="btn btn--sm btn--ghost">Delete</button>
              </form>
            </span>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php elseif ($gate2Unlocked): ?>
        <p class="mt-md">No offers yet. Click “Create a new offer” to make your first one.</p>
      <?php endif; ?>

      <p class="mt-lg"><a class="btn btn--ghost" href="/dashboard.php">Back to dashboard</a></p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../inc/footer.php'; ?>
