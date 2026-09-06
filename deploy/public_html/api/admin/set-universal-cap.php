<?php
/**
 * POST /api/admin/set-universal-cap.php  {code_id, max_uses}  ->  {ok, max_uses}
 * Admin only. Sets a universal (shared) code's slot cap — the number of sign-ups
 * it allows before it refuses new ones and emails the admins to rotate it.
 * An empty / zero max_uses removes the cap (unlimited).
 */

declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/guard.php';
require_once __DIR__ . '/../../inc/codes.php';

api_require_admin();
require_post();
csrf_check();

if (!access_codes_slots_supported(db())) {
    fail('Slots are not enabled yet. Run the universal-code-slots migration on the database first.', 409);
}

$in     = json_input();
$codeId = (int)($in['code_id'] ?? 0);
$raw    = $in['max_uses'] ?? null;
$max    = ($raw === '' || $raw === null) ? null : (int)$raw;

if ($codeId < 1) {
    fail('Missing code.');
}
if ($max !== null && ($max < 1 || $max > 1000000)) {
    fail('Enter a slot number between 1 and 1,000,000, or leave it blank for unlimited.');
}

set_code_max_uses(db(), $codeId, $max);

// Report back the stored value plus how many slots remain.
$used = code_use_count(db(), $codeId);
json_out([
    'ok'         => true,
    'max_uses'   => $max,
    'used'       => $used,
    'slots_left' => $max === null ? null : max(0, $max - $used),
    'full'       => $max !== null && $used >= $max,
]);
