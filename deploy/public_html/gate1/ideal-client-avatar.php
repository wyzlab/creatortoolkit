<?php
/**
 * gate1/ideal-client-avatar.php
 * Server-side guard first, then the shared tool renderer.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';
require_gate(1);
$slug = 'ideal-client-avatar';
require __DIR__ . '/../inc/render-tool.php';
