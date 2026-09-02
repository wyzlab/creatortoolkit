<?php
/**
 * gate2/course-design-checklist.php
 * Server-side guard first, then the shared tool renderer.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';
require_gate(2);
$slug = 'course-design-checklist';
require __DIR__ . '/../inc/render-tool.php';
