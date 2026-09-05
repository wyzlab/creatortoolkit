<?php
/**
 * footer.php — bottom of every rendered page. Closes the main content,
 * renders the site footer, embeds the WyzAI floating widget, and loads
 * the shared scripts.
 *
 * The per-tool Free Resource Terms of Use and Proof of Authorship blocks
 * live in result-blocks.php and appear on result pages and emails, not here.
 */

declare(strict_types=1);

$nonce = $GLOBALS['CSP_NONCE'] ?? '';
?>
  </main>

  <footer class="site-footer">
    <div class="wrap site-footer__inner">
      <div class="site-footer__brand">
        <strong><?= e(APP['brand_line']) ?></strong>
        <p class="site-footer__tag">Learn the path. Build the future.</p>
      </div>
      <div class="site-footer__meta">
        <p>Questions? <a href="mailto:<?= e(APP['contact_email']) ?>"><?= e(APP['contact_email']) ?></a></p>
        <p class="site-footer__copy">&copy; <?= date('Y') ?> WyzLab Solutions OPC. Yours to use, not for resale.</p>
      </div>
    </div>
  </footer>

  <!-- WyzAI floating assistant (blue chat button, bottom-right of every page). -->
  <script
    src="<?= e(APP['wyzai_widget_src']) ?>"
    data-agency="<?= e(APP['wyzai_agency_id']) ?>"
    defer></script>

  <script src="<?= e(asset('/js/main.js')) ?>" defer></script>
  <?php if (!empty($pageScripts) && is_array($pageScripts)): ?>
    <?php foreach ($pageScripts as $src): ?>
      <script src="<?= e(asset($src)) ?>" defer></script>
    <?php endforeach; ?>
  <?php endif; ?>
</body>
</html>
