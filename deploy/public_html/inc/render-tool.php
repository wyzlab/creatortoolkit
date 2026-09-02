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
    'title'       => $def['title'],
    'lede'        => $def['lede'] ?? '',
    'productId'   => (int)$reg['product_id'],
    'publishedOn' => $reg['published_on'],
    'wyzaiPrompt' => $def['wyzai_prompt'] ?? '',
    'scoring'     => $def['scoring'] ?? ['type' => 'none'],
    'steps'       => $def['steps'],
];

$pageTitle = $def['title'];
$pageDesc  = $def['lede'] ?? '';
require __DIR__ . '/head.php';
?>
<div class="wrap" data-tool-root data-tool-slug="<?= e($slug) ?>"></div>

<script type="application/json" id="tool-config"><?= json_encode($clientConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="module" src="<?= e(asset('/js/tool-engine.js')) ?>"></script>
<?php require __DIR__ . '/footer.php'; ?>
