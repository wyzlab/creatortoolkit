<?php
/**
 * POST /api/admin/gen-codes.php  {count, batch_label}  ->  {ok, codes:[...]}
 * Admin only. Mints a batch of unassigned codes and returns them once.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/guard.php';
require_once __DIR__ . '/../../inc/codes.php';

api_require_admin();
require_post();
csrf_check();

$in    = json_input();
$count = (int)($in['count'] ?? 0);
$batch = trim((string)($in['batch_label'] ?? 'admin')) ?: 'admin';

if ($count < 1 || $count > 500) {
    fail('Choose a number of codes between 1 and 500.');
}

$codes = mint_codes(db(), $count, $batch);
json_out(['ok' => true, 'batch_label' => $batch, 'count' => count($codes), 'codes' => $codes]);
