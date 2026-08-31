<?php
/**
 * result-blocks.php — the Free Resource Terms of Use and Proof of Authorship
 * blocks. Every result page and every result email carries both, with the
 * correct per-tool product id.
 *
 * Wording is taken verbatim from the published source PDFs, with em dashes
 * removed per the brand voice rule (hyphens only, restructure the sentence).
 *
 * These functions return HTML strings so the same markup can be echoed into
 * a page or embedded in an email body.
 */

declare(strict_types=1);

require_once __DIR__ . '/tools.php';

/** The checkout / sales link for a tool, from its product id. */
function tool_sales_link(string $slug): string
{
    $t = tool($slug);
    $pid = $t['product_id'] ?? 0;
    return 'https://www.wyzcore.com/en/checkout/?product_id=' . $pid . '&product_type=digital_product';
}

/** Free Resource Terms of Use. Same for every tool. */
function render_terms_of_use(): string
{
    return <<<HTML
<section class="legal-block legal-block--terms" aria-label="Free Resource Terms of Use">
  <h3 class="legal-block__title">Free Resource, Terms of Use</h3>
  <p class="legal-block__lede">Yours to use. Not yours to sell. A WyzLab Studio Original.</p>
  <div class="legal-block__cols">
    <div>
      <h4>You may</h4>
      <ul>
        <li>Use it for your own personal or business purposes.</li>
        <li>Share the official download link so others can get their own free copy.</li>
      </ul>
    </div>
    <div>
      <h4>You may not</h4>
      <ul>
        <li>Sell, resell, or charge for it in any form.</li>
        <li>Bundle it with a paid product, membership, or offer.</li>
        <li>Rebrand, rename, or present it as your own. No PLR or white-label.</li>
        <li>Redistribute the file itself or give it away as your own. Share the official link instead.</li>
      </ul>
    </div>
  </div>
  <p class="legal-block__fine">&copy; 2026 WyzLab Studio Originals, the publishing arm of WyzLab Solutions OPC. All rights reserved.</p>
</section>
HTML;
}

/** Proof of Authorship, filled with the tool's product id and publish date. */
function render_proof_of_authorship(string $slug): string
{
    $t = tool($slug);
    $published = e($t['published_on'] ?? '');
    $version   = e($t['version'] ?? '1.1');
    $link      = e(tool_sales_link($slug));

    return <<<HTML
<section class="legal-block legal-block--proof" aria-label="Proof of Authorship">
  <h3 class="legal-block__title">Proof of Authorship</h3>
  <p class="legal-block__lede">Made by WyzLab. Keep this on file.</p>
  <p>This resource was created and published by WyzLab Studio Originals, the publishing arm of WyzLab Solutions OPC. Original source files, version history, and first-publish records are retained as proof of ownership. Copyright is asserted under Philippine and international law.</p>
  <dl class="legal-block__record">
    <dt>Created by</dt><dd>Yza Santiago for WyzLab Studio Originals</dd>
    <dt>First published</dt><dd>{$published}</dd>
    <dt>Official source and sales link</dt><dd><a href="{$link}">{$link}</a></dd>
    <dt>Version reference</dt><dd>version {$version}</dd>
  </dl>
  <p class="legal-block__fine">Verify authorized use or report misuse: hello@wyzcore.com</p>
</section>
HTML;
}

/** Both blocks together, the standard result-page and email footer. */
function render_legal_blocks(string $slug): string
{
    return render_terms_of_use() . "\n" . render_proof_of_authorship($slug);
}
