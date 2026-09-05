<?php
/**
 * gate2/content-to-digital-product-sparker.php
 * Server-side guard first, then the shared tool renderer.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';
require_gate(2);
$slug = 'content-to-digital-product-sparker';
require __DIR__ . '/../inc/render-tool.php';
