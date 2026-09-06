<?php
/**
 * POST /api/admin/set-integrations.php
 *   { signature_token?, access_token?, clear_signature?, clear_access? }
 *   -> { ok, signature_set, access_set, live }
 *
 * Admin only. Stores the wyzcore webhook tokens in the database (auto-creating
 * the app_settings table) so they can be set from the browser without any
 * server filesystem access. A blank field leaves the existing value unchanged;
 * send clear_* to remove one. Tokens are never echoed back.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/guard.php';
require_once __DIR__ . '/../../inc/settings.php';

api_require_admin();
require_post();
csrf_check();

$in  = json_input();
$sig = trim((string)($in['signature_token'] ?? ''));
$acc = trim((string)($in['access_token'] ?? ''));
$clearSig = !empty($in['clear_signature']);
$clearAcc = !empty($in['clear_access']);

// Basic sanity: tokens are short opaque strings. Reject anything absurd so a
// paste accident can't store megabytes.
foreach (['signature' => $sig, 'access' => $acc] as $label => $val) {
    if ($val !== '' && (strlen($val) < 8 || strlen($val) > 512)) {
        fail(ucfirst($label) . ' token looks wrong — it should be the short random string from wyzcore.');
    }
}

try {
    if ($clearSig)          { setting_set(db(), 'wyzcore_signature_token', null); }
    elseif ($sig !== '')    { setting_set(db(), 'wyzcore_signature_token', $sig); }

    if ($clearAcc)          { setting_set(db(), 'wyzcore_access_token', null); }
    elseif ($acc !== '')    { setting_set(db(), 'wyzcore_access_token', $acc); }
} catch (\Throwable $e) {
    fail($e->getMessage(), 500);
}

$secrets = require CONFIG_DIR . '/secrets.php';
$sigNow  = token_setting(db(), 'wyzcore_signature_token', (string)($secrets['wyzcore_signature_token'] ?? ''));
$accNow  = token_setting(db(), 'wyzcore_access_token',    (string)($secrets['wyzcore_access_token'] ?? ''));
$sigSet  = token_is_set($sigNow);
$accSet  = token_is_set($accNow);

json_out([
    'ok'            => true,
    'signature_set' => $sigSet,
    'access_set'    => $accSet,
    'live'          => $sigSet || $accSet,
]);
