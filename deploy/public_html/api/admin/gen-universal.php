<?php
/**
 * POST /api/admin/gen-universal.php  ->  {ok, code}
 * Admin only. Creates a single UNIVERSAL access code (batch "__universal__")
 * that any buyer can use, so a static WyzCore email can carry one code for
 * everyone. Rotating revokes the previous universal code and issues a new one,
 * so a leaked code can be swapped instantly.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/guard.php';
require_once __DIR__ . '/../../inc/codes.php';

api_require_admin();
require_post();
csrf_check();

$pdo = db();
try {
    $pdo->beginTransaction();
    // Revoke any existing active universal code (rotation).
    $pdo->prepare("UPDATE access_codes SET status='revoked'
                    WHERE batch_label='__universal__' AND status='unclaimed'")
        ->execute();
    $codes = mint_codes($pdo, 1, '__universal__');
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('gen-universal failed: ' . $e->getMessage());
    fail('Could not create the universal code just now. Please try again.', 500);
}

if (!$codes) {
    fail('Could not create the universal code just now. Please try again.', 500);
}
json_out(['ok' => true, 'code' => $codes[0]]);
