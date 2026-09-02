<?php
/**
 * gate3/discovery-call-script.php
 * Server-side guard first, then the shared tool renderer.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';
require_gate(3);
$slug = 'discovery-call-script';
require __DIR__ . '/../inc/render-tool.php';
