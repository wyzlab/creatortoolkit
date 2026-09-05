<?php
/**
 * results/gate.php?gate=N — the gate summary. Shows the summary, the WyzAI
 * coach code once, and the PDFs unlocked in this gate.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';
require_once __DIR__ . '/../inc/gates.php';

require_login();
$user = current_user();
$uid  = (int)$user['id'];

$gate = (int)($_GET['gate'] ?? 0);
if (!isset(GATES[$gate])) { redirect('/dashboard.php'); }

// The gate must be complete. If its tools are all done but it was not closed
// yet, close it now (idempotent). Otherwise send them back to the dashboard.
$pdo = db();
$gr = $pdo->prepare('SELECT summary_html, ai_paragraph FROM gate_results WHERE user_id = ? AND gate_number = ? LIMIT 1');
$gr->execute([$uid, $gate]);
$summaryRow = $gr->fetch();

$handover = null;
$aiParagraph = null;
if (!$summaryRow) {
    try {
        $pdo->beginTransaction();
        $handover = close_gate($pdo, $uid, $gate);
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
    if ($handover === null) { redirect('/dashboard.php?locked=' . $gate); }
    // Post-commit: AI coach note + gate email.
    $handover = finalize_gate_after_commit($pdo, $uid, $gate, $handover);
    $summaryHtml = $handover['summary_html'];
    $aiParagraph = $handover['ai_paragraph'] ?? null;
    $coach = $handover['coach_name'];
    $code  = $handover['wyzai_code'];
} else {
    $summaryHtml = $summaryRow['summary_html'];
    $aiParagraph = $summaryRow['ai_paragraph'] ?? null;
    // Fetch the coach code already claimed for this gate (no new slot).
    $c = $pdo->prepare('SELECT c.code, c.coach_name FROM wyzai_code_claims cl
                         JOIN wyzai_codes c ON c.id = cl.wyzai_code_id
                        WHERE cl.user_id = ? AND cl.trigger_key = ? LIMIT 1');
    $c->execute([$uid, 'gate_' . $gate]);
    $cc = $c->fetch() ?: [];
    $coach = $cc['coach_name'] ?? null;
    $code  = $cc['code'] ?? null;
}

// Unlocked PDFs in this gate.
$pdfs = [];
foreach (tools_in_gate($gate) as $slug => $t) {
    $q = $pdo->prepare('SELECT id FROM pdf_unlocks WHERE user_id = ? AND tool_slug = ? LIMIT 1');
    $q->execute([$uid, $slug]);
    if ($q->fetch()) { $pdfs[$slug] = $t['title']; }
}

$hasRealCode = $code && strpos($code, 'PLACEHOLDER') === false;

$pageTitle = GATES[$gate]['label'] . ' summary';
require __DIR__ . '/../inc/head.php';
?>
<div class="wrap wrap--narrow">
  <div class="tool-shell">
    <div class="tool-shell__head">
      <span class="badge badge--done">Gate <?= $gate ?> complete</span>
    </div>

    <div class="tool-step">
      <?= $summaryHtml /* built server-side from a whitelist, already escaped */ ?>

      <?php if (ai_notes_enabled() && $aiParagraph !== null && trim($aiParagraph) !== ''): ?>
      <div class="coach-note">
        <h3>A note from your coach</h3>
        <p><?= nl2br(e($aiParagraph)) ?></p>
      </div>
      <?php endif; ?>

      <?php require __DIR__ . '/../inc/ask-wyzai.php'; ?>

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
        <h3>Your downloads are unlocked</h3>
        <ul class="tool-list">
          <?php foreach ($pdfs as $slug => $title): ?>
          <li class="tool-row tool-row--done">
            <div class="tool-row__main">
              <span class="tool-row__status" aria-hidden="true">&#10003;</span>
              <span class="tool-row__name"><?= e($title) ?></span>
            </div>
            <a class="btn btn--sm btn--primary" href="/api/download-pdf.php?tool_slug=<?= e($slug) ?>">Download PDF</a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <p class="mt-lg">
        <a class="btn btn--cta" href="/dashboard.php">Back to your dashboard</a>
      </p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../inc/footer.php'; ?>
