<?php
/**
 * GET /api/admin/get-integrations.php -> { webhook_url, signature_set, access_set, live }
 * Admin only. Reports whether the wyzcore webhook tokens are configured, WITHOUT
 * ever returning the token values themselves (they never leave the server).
 */

declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/guard.php';
require_once __DIR__ . '/../../inc/settings.php';

api_require_admin();

$secrets  = require CONFIG_DIR . '/secrets.php';
$sig      = token_setting(db(), 'wyzcore_signature_token', (string)($secrets['wyzcore_signature_token'] ?? ''));
$acc      = token_setting(db(), 'wyzcore_access_token',    (string)($secrets['wyzcore_access_token'] ?? ''));
$sigSet   = token_is_set($sig);
$accSet   = token_is_set($acc);

json_out([
    'webhook_url'   => rtrim((string)(APP['app_url'] ?? ''), '/') . '/api/wyzcore-webhook.php',
    'signature_set' => $sigSet,
    'access_set'    => $accSet,
    // Live once at least one credential is set (the webhook can then verify).
    'live'          => $sigSet || $accSet,
]);
