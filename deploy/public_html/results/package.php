<?php
/**
 * results/package.php — the full package: everything the learner built, the
 * Community Designer handover, and all unlocked PDFs. Available once Gate 3
 * is complete.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';
require_once __DIR__ . '/../inc/gates.php';

require_login();
$user = current_user();
$uid  = (int)$user['id'];
$pdo  = db();

// Gate 3 must be complete.
$g3 = $pdo->prepare('SELECT completed_at FROM gate_progress WHERE user_id = ? AND gate_number = 3 LIMIT 1');
$g3->execute([$uid]);
$row = $g3->fetch();
if (!$row || $row['completed_at'] === null) {
    redirect('/dashboard.php');
}

// Ensure the package is closed, generate the AI note, and email once (idempotent).
$handover = finalize_package_after_commit($pdo, $uid);

$summaryHtml = $handover['summary_html'] ?? '';
$aiParagraph = $handover['ai_paragraph'] ?? null;
$coach = $handover['coach_name'] ?? null;
$code  = $handover['wyzai_code'] ?? null;
$hasRealCode = $code && strpos($code, 'PLACEHOLDER') === false;

// All unlocked PDFs.
$pdfs = [];
$q = $pdo->prepare('SELECT tool_slug FROM pdf_unlocks WHERE user_id = ?');
$q->execute([$uid]);
foreach ($q->fetchAll() as $r) {
    $t = tool($r['tool_slug']);
    if ($t) { $pdfs[$r['tool_slug']] = $t['title']; }
}

$pageTitle = 'Your full package';
require __DIR__ . '/../inc/head.php';
?>
<div class="wrap wrap--narrow">
  <div class="tool-shell">
    <div class="tool-shell__head">
      <span class="badge badge--done">Toolkit complete</span>
    </div>
    <div class="tool-step">
      <?= $summaryHtml ?>

      <?php if ($aiParagraph !== null && trim($aiParagraph) !== ''): ?>
      <div class="coach-note">
        <h3>A note from your coach</h3>
        <p><?= nl2br(e($aiParagraph)) ?></p>
      </div>
      <?php endif; ?>

      <?php if ($coach): ?>
      <div class="coach-handover">
        <h3>Meet your <?= e($coach) ?></h3>
        <?php if ($hasRealCode): ?>
          <p>Use this code once to unlock your coach inside WyzAI.</p>
          <p class="coach-handover__code"><?= e($code) ?></p>
        <?php else: ?>
          <p>Your <?= e($coach) ?> is being set up. Your code will appear here once the WyzAI agency is live.</p>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($pdfs): ?>
      <div class="unlocked-pdfs">
        <h3>All your downloads</h3>
        <ul class="tool-list">
          <?php foreach ($pdfs as $slug => $title): ?>
          <li class="tool-row tool-row--done">
            <div class="tool-row__main"><span class="tool-row__status" aria-hidden="true">&#10003;</span><span class="tool-row__name"><?= e($title) ?></span></div>
            <a class="btn btn--sm btn--primary" href="/api/download-pdf.php?tool_slug=<?= e($slug) ?>">Download</a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <p class="mt-lg"><a class="btn btn--ghost" href="/dashboard.php">Back to dashboard</a></p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../inc/footer.php'; ?>
