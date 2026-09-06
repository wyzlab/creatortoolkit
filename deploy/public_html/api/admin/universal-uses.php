<?php
/**
 * GET /api/admin/universal-uses.php  ->  {tracked, total, groups:[...]}
 * Admin only. Every universal (shared) code, each with the sign-ups that used
 * it — email and date — so the admin can reconcile against purchases and keep
 * the users of one code separate from another after a rotation.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/guard.php';
require_once __DIR__ . '/../../inc/codes.php';

api_require_admin();
$pdo = db();

$groups = universal_codes_with_uses($pdo);
$total  = 0;
foreach ($groups as $g) { $total += (int)$g['count']; }

json_out([
    'tracked' => code_redemptions_supported($pdo),
    'total'   => $total,
    'groups'  => $groups,
]);
