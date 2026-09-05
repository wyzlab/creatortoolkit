<?php
/**
 * render-tool.php — renders an interactive tool page from its canonical
 * definition. The page emits the definition as JSON; the engine (loaded as an
 * ES module) reads it and renders. No inline JavaScript, so the CSP stays strict.
 *
 * Expects $slug to be set and the gate guard to have run already.
 */

declare(strict_types=1);
require_once __DIR__ . '/tooldefs.php';
require_once __DIR__ . '/tools.php';

$def = tool_def($slug);
$reg = tool($slug);
if (!$def || !$reg) { http_response_code(404); exit('Unknown tool.'); }

// The client config: rendering fields only. No writesToProfile or prefillFrom
// needed on the client; the server resolves carry-forward in get-profile.
$clientConfig = [
    'slug'        => $slug,
    'gate'        => (int)$def['gate'],
    'gateLabel'   => GATES[(int)$def['gate']]['label'] ?? '',
    'title'       => $def['title'],
    'lede'        => $def['lede'] ?? '',
    'productId'   => (int)$reg['product_id'],
    'publishedOn' => $reg['published_on'],
    'wyzaiPrompt' => $def['wyzai_prompt'] ?? '',
    'scoring'     => $def['scoring'] ?? ['type' => 'none'],
    'steps'       => $def['steps'],
    // ?again=1 starts a blank run (carry-forward from the profile still applies),
    // used by "create another offer" so a new offer does not reload the last one.
    'fresh'       => !empty($_GET['again']),
];

// Does this tool use the fee calculator? If so, deliver the fee table and the
// calculator script (the client reads the same rows the server uses).
$needsFees = false;
foreach ($def['steps'] as $step) {
    foreach ($step['fields'] as $f) {
        if (($f['type'] ?? '') === 'fee-calculator') { $needsFees = true; break 2; }
    }
}
$feeTable = [];
if ($needsFees) {
    require_once __DIR__ . '/fees.php';
    $feeTable = array_values(load_fees(db()));
}

// The learner's build title (default "Offer 1"), shown atop every tool so they
// always know which offer they are working on.
$buildTitle = 'Offer 1';
if (function_exists('current_user') && ($cu = current_user())) {
    $bt = db()->prepare('SELECT profile_json FROM user_profile WHERE user_id = ? LIMIT 1');
    $bt->execute([(int)$cu['id']]);
    $btProfile = json_decode((string)$bt->fetchColumn() ?: '{}', true) ?: [];
    if (trim((string)($btProfile['journey_title'] ?? '')) !== '') {
        $buildTitle = (string)$btProfile['journey_title'];
    }
}

$pageTitle = $def['title'];
$pageDesc  = $def['lede'] ?? '';
require __DIR__ . '/head.php';
?>
<div class="wrap build-banner">
  <span class="build-banner__label">This build</span>
  <span class="build-banner__name"><?= e($buildTitle) ?></span>
</div>
<div class="wrap" data-tool-root data-tool-slug="<?= e($slug) ?>"></div>

<script type="application/json" id="tool-config"><?= json_encode($clientConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php if ($needsFees): ?>
<script type="application/json" id="fee-table"><?= json_encode($feeTable, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= e(asset('/js/fee-calc.js')) ?>"></script>
<?php endif; ?>
<script type="module" src="<?= e(asset('/js/tool-engine.js')) ?>"></script>
<?php require __DIR__ . '/footer.php'; ?>
