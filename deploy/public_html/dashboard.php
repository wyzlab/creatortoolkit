<?php
/**
 * dashboard.php — the three-gate journey map. Gate 1 opens at account
 * creation; each next gate opens when every tool in the one before is done.
 */

declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/guard.php';

require_login();
$user = current_user();
$uid  = (int)$user['id'];

// The learner's editable build title, shown above the gates (default "Offer 1").
$pfRow = db()->prepare('SELECT profile_json FROM user_profile WHERE user_id = ? LIMIT 1');
$pfRow->execute([$uid]);
$profileArr   = json_decode((string)$pfRow->fetchColumn() ?: '{}', true) ?: [];
$journeyTitle = trim((string)($profileArr['journey_title'] ?? '')) !== '' ? (string)$profileArr['journey_title'] : 'Offer 1';

// Gate progress rows, keyed by gate number.
$gp = [];
$stmt = db()->prepare('SELECT gate_number, tools_required, tools_completed, unlocked_at, completed_at
                         FROM gate_progress WHERE user_id = ?');
$stmt->execute([$uid]);
foreach ($stmt->fetchAll() as $r) {
    $gp[(int)$r['gate_number']] = $r;
}

// Tool sessions, keyed by slug.
$ts = [];
$stmt = db()->prepare('SELECT tool_slug, status FROM tool_sessions WHERE user_id = ?');
$stmt->execute([$uid]);
foreach ($stmt->fetchAll() as $r) {
    $ts[$r['tool_slug']] = $r['status'];
}

$lockedNotice = isset($_GET['locked']) ? (int)$_GET['locked'] : 0;

$pageTitle = 'Your dashboard';
$pageDesc  = 'Your three-gate path through the DIY Creator Starter Toolkit.';
require __DIR__ . '/inc/head.php';
?>
<div class="wrap">
  <div class="tool-shell__head">
    <span class="badge badge--studio">Studio Original</span>
    <h1>Your path, one gate at a time</h1>
    <p class="muted">Finish every tool in a gate to open the next. Your answers carry forward, so each tool starts you further along.</p>
  </div>

  <?php if ($lockedNotice): ?>
    <div class="notice notice--stale">
      <span>That step is still locked. Finish the gate before it to open it.</span>
    </div>
  <?php endif; ?>

  <div class="build-title" data-build-title>
    <label class="build-title__label" for="build-title-input">This build</label>
    <div class="build-title__row">
      <input class="input build-title__input" id="build-title-input" value="<?= e($journeyTitle) ?>" maxlength="120" aria-label="Name this build">
      <button type="button" class="btn btn--sm btn--primary" data-save-build-title>Save name</button>
      <span class="autosave-flag" data-build-title-flag></span>
    </div>
  </div>

  <div class="journey">
    <?php foreach (GATES as $n => $gate):
        $row = $gp[$n] ?? null;
        $unlocked  = $row && $row['unlocked_at'] !== null;
        $completed = $row && $row['completed_at'] !== null;
        $done      = (int)($row['tools_completed'] ?? 0);
        $need      = (int)($gate['tools_required']);
        $stateClass = $completed ? 'gate-card--done' : ($unlocked ? 'gate-card--open' : 'gate-card--locked');
        $stateBadge = $completed
            ? ['badge--done', 'Complete']
            : ($unlocked ? ['badge--open', 'Open'] : ['badge--locked', 'Locked']);
    ?>
    <section class="gate-card <?= $stateClass ?>" aria-label="Gate <?= $n ?>: <?= e($gate['label']) ?>">
      <div class="gate-card__head">
        <div>
          <div class="gate-card__num">GATE <?= $n ?></div>
          <div class="gate-card__title"><?= e($gate['label']) ?></div>
          <div class="gate-card__wording"><?= $done ?> of <?= $need ?> tools done</div>
        </div>
        <span class="badge <?= $stateBadge[0] ?>"><?= $stateBadge[1] ?></span>
      </div>
      <div class="gate-card__body">
        <div class="progress" role="progressbar" aria-valuenow="<?= $need ? round($done / $need * 100) : 0 ?>" aria-valuemin="0" aria-valuemax="100">
          <div class="progress__fill" style="width: <?= $need ? round($done / $need * 100) : 0 ?>%"></div>
        </div>

        <?php
          // Pick-one groups: which slugs are alternatives, and is the group filled.
          $pickSatisfied = [];   // slug => true if this slug's group is already satisfied
          $pickMember    = [];   // slug => true if it belongs to a pick-one group
          foreach (gate_choose_one_groups($n) as $group) {
              $groupDone = false;
              foreach ($group as $s) { if (($ts[$s] ?? '') === 'completed') { $groupDone = true; break; } }
              foreach ($group as $s) { $pickMember[$s] = true; $pickSatisfied[$s] = $groupDone; }
          }
        ?>
        <ul class="tool-list mt-lg">
          <?php foreach (tools_in_gate($n) as $slug => $tool):
              $status = $ts[$slug] ?? 'not_started';
              $isPick = !empty($pickMember[$slug]);
              // A pick-one alternative the learner did not choose (the other is done).
              $pickedOther = $isPick && $status !== 'completed' && !empty($pickSatisfied[$slug]);
              $rowClass = $status === 'completed' ? 'tool-row--done'
                        : ($status === 'in_progress' ? 'tool-row--progress' : '');
              $mark = $status === 'completed' ? '&#10003;'
                    : ($status === 'in_progress' ? '&hellip;' : '');
              $href = '/gate' . $n . '/' . $slug . '.php';
          ?>
          <li class="tool-row <?= $rowClass ?>">
            <div class="tool-row__main">
              <span class="tool-row__status" aria-hidden="true"><?= $mark ?></span>
              <div>
                <div class="tool-row__name"><?= e($tool['title']) ?><?php if ($isPick): ?> <span class="badge badge--pick">Pick one</span><?php endif; ?></div>
                <div class="tool-row__meta">
                  <?php if ($pickedOther): ?>
                    Optional, you picked the other Sparker
                  <?php else: ?>
                    <?= $status === 'completed' ? 'Done, still editable'
                       : ($status === 'in_progress' ? 'In progress, pick up where you left off'
                       : ($isPick ? 'Not started, course or digital product' : 'Not started')) ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php if ($unlocked): ?>
              <a class="btn btn--sm <?= $status === 'not_started' ? 'btn--primary' : 'btn--ghost' ?>" href="<?= e($href) ?>">
                <?= $status === 'completed' ? 'Review' : ($status === 'in_progress' ? 'Continue' : 'Start') ?>
              </a>
            <?php else: ?>
              <span class="badge badge--locked">Locked</span>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>

        <?php if ($completed): ?>
          <p class="mt-lg"><a class="btn btn--ghost" href="/results/gate.php?gate=<?= $n ?>">See your Gate <?= $n ?> summary</a></p>
        <?php endif; ?>
      </div>
    </section>
    <?php endforeach; ?>
  </div>

  <?php
    $allDone = isset($gp[3]) && $gp[3]['completed_at'] !== null;
    if ($allDone): ?>
    <p class="mt-lg text-center"><a class="btn btn--cta btn--lg" href="/results/package.php">Open your full package</a></p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
