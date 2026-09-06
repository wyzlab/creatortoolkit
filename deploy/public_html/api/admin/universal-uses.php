<?php
/**
 * GET /api/admin/universal-uses.php  ->  {count, uses:[{email, redeemed_at}], tracked}
 * Admin only. How many sign-ups used the shared universal code, and the email
 * plus date of each, so the admin can match them against actual purchases.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/guard.php';
require_once __DIR__ . '/../../inc/codes.php';

api_require_admin();
$pdo = db();

$tracked = code_redemptions_supported($pdo);
$uses = universal_redemptions($pdo);

json_out([
    'tracked' => $tracked,
    'count'   => universal_use_count($pdo),
    'uses'    => array_map(fn($r) => [
        'email'       => $r['email'],
        'redeemed_at' => $r['redeemed_at'],
    ], $uses),
]);
