<?php
/**
 * offers/view.php?id=N — one saved offer, laid out for printing. The "Print /
 * Save as PDF" button opens the browser print dialog; @media print strips the
 * site chrome so the PDF is just the offer.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';
require_once __DIR__ . '/../inc/offers.php';

require_login();
$user = current_user();
$uid  = (int)$user['id'];

$id    = (int)($_GET['id'] ?? 0);
$offer = get_offer(db(), $uid, $id);
if (!$offer) { redirect('/offers/'); }

$pageTitle = $offer['title'];
require __DIR__ . '/../inc/head.php';
?>
<div class="wrap wrap--narrow">
  <div class="offer-actions no-print">
    <a class="btn btn--ghost" href="/offers/">&larr; My Offers</a>
    <button type="button" class="btn btn--cta" data-print>Print / Save as PDF</button>
    <button type="button" class="btn btn--primary" data-email-offer="<?= (int)$offer['id'] ?>">Email this to me</button>
    <a class="btn btn--ghost" href="/gate2/one-page-offer.php?again=1">Create another offer</a>
  </div>
  <div class="notice no-print" data-email-offer-notice hidden></div>

  <article class="offer-print">
    <header class="offer-print__head">
      <p class="offer-print__brand"><?= e(APP['academy_line']) ?></p>
      <h1 class="offer-print__title"><?= e($offer['title']) ?></h1>
      <p class="offer-print__date">Saved <?= e(date('F j, Y', strtotime((string)$offer['created_at']))) ?></p>
    </header>
    <?= $offer['result_html'] /* built server-side from a whitelist when the offer was saved */ ?>
  </article>
</div>
<?php require __DIR__ . '/../inc/footer.php'; ?>
