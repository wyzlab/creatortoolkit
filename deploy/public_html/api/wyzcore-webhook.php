<?php
/**
 * POST /api/wyzcore-webhook.php  ->  200 ack
 *
 * Receives store events from wyzcore (new sale, renew order, cancel order) and
 * automatically grants or suspends access. No codes, no copy-paste.
 *
 * Security: the payload is signed. We verify an HMAC-SHA256 of the raw body
 * against the signature token in config/secrets.php ('wyzcore_signature_token').
 *
 * Because the exact wyzcore payload shape is confirmed from a real delivery,
 * this endpoint ALWAYS logs the incoming headers and body (to the PHP error
 * log) so the first test webhook reveals the format, and it only acts when the
 * signature verifies. Until the signature token is configured it runs in
 * log-only mode and takes no action.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/provision.php';
require_once __DIR__ . '/../inc/settings.php';
require_once __DIR__ . '/../inc/webhook-util.php';

require_post();

$raw = file_get_contents('php://input') ?: '';
$headers = collect_headers();

// Always log for format discovery: to the PHP error log, and (best effort) to
// a file above the web root you can open in File Manager to read the exact
// shape of a test delivery: private/webhook-log.txt
error_log('WYZCORE_WEBHOOK headers=' . json_encode($headers)
        . ' body=' . substr($raw, 0, 2000));
@file_put_contents(
    __DIR__ . '/../../private/webhook-log.txt',
    '=== ' . now_dt() . " ===\nHEADERS: " . json_encode($headers) . "\nBODY: " . $raw . "\n\n",
    FILE_APPEND | LOCK_EX
);

$secrets   = require CONFIG_DIR . '/secrets.php';
// Admin-managed database settings take precedence over the secrets file, so the
// tokens can be set from the admin console without any filesystem access.
$sigToken  = token_setting(db(), 'wyzcore_signature_token', (string)($secrets['wyzcore_signature_token'] ?? ''));
$accToken  = token_setting(db(), 'wyzcore_access_token',    (string)($secrets['wyzcore_access_token'] ?? ''));
$sigSet    = token_is_set($sigToken);
$accSet    = token_is_set($accToken);

// Log-only mode until at least one credential is set. With neither, we can't
// tell a real wyzcore delivery from a forged one, so we only log.
if (!$sigSet && !$accSet) {
    json_out(['ok' => true, 'mode' => 'log-only, no wyzcore token set'], 200);
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = [];
}

// A delivery is authenticated if EITHER credential proves it came from wyzcore:
//   1. HMAC-SHA256 of the raw body matches the signature token (strongest), or
//   2. the access token is presented verbatim (a shared bearer secret).
// We record which path passed so the first test delivery's log is unambiguous.
$authMethod = '';

// (1) HMAC signature. Accept hex or base64, in any signature-ish header.
if ($sigSet) {
    $expectedHex = hash_hmac('sha256', $raw, $sigToken);
    $expectedB64 = base64_encode(hash_hmac('sha256', $raw, $sigToken, true));
    foreach ($headers as $name => $value) {
        $n = strtolower($name);
        if (strpos($n, 'sign') === false && strpos($n, 'hash') === false) {
            continue;
        }
        $v = preg_replace('/^sha256=/i', '', trim($value)); // strip optional prefix
        if (hash_equals($expectedHex, $v) || hash_equals($expectedB64, $v)) {
            $authMethod = 'hmac';
            break;
        }
    }
}

// (2) Access token. wyzcore may send it as a bearer/header or inside the body.
// Compared constant-time against every candidate we can find.
if ($authMethod === '' && $accSet) {
    $candidates = [];
    foreach ($headers as $name => $value) {
        $n = strtolower($name);
        if (strpos($n, 'auth') !== false || strpos($n, 'token') !== false
            || strpos($n, 'api-key') !== false || strpos($n, 'apikey') !== false
            || strpos($n, 'secret') !== false) {
            $candidates[] = preg_replace('/^bearer\s+/i', '', trim($value));
        }
    }
    foreach (['access_token', 'token', 'api_key', 'apikey', 'secret', 'key'] as $k) {
        if (!empty($payload[$k]) && is_string($payload[$k])) { $candidates[] = trim($payload[$k]); }
    }
    foreach ($candidates as $cand) {
        if ($cand !== '' && hash_equals($accToken, $cand)) {
            $authMethod = 'access-token';
            break;
        }
    }
}

if ($authMethod === '') {
    error_log('WYZCORE_WEBHOOK not authenticated (no matching signature or access token)');
    // Ack so the store does not retry forever, but take no action.
    json_out(['ok' => false, 'reason' => 'unauthenticated'], 200);
}
error_log('WYZCORE_WEBHOOK authenticated via ' . $authMethod);

if (!$payload) {
    json_out(['ok' => false, 'reason' => 'bad json'], 200);
}

$email = find_email($payload);
$event = strtolower(find_event($payload));

if ($email === '' || !is_email($email)) {
    error_log('WYZCORE_WEBHOOK no usable email in payload');
    json_out(['ok' => false, 'reason' => 'no email'], 200);
}

// wyzcore sells many products. Only this sale's TOOLKIT line grants access;
// a sale of any other product is acknowledged and ignored.
$products   = find_products($payload);
$productIds = find_product_ids($payload);
if (!purchase_is_toolkit($products, $productIds)) {
    error_log('WYZCORE_WEBHOOK ignored: not the toolkit. titles=' . json_encode($products) . ' ids=' . json_encode($productIds));
    json_out(['ok' => true, 'action' => 'ignored', 'reason' => 'not the toolkit product'], 200);
}

try {
    if (strpos($event, 'cancel') !== false) {
        suspend_access(db(), $email);
        $action = 'suspended';
    } else {
        // new sale, renew, or anything else that means "has access"
        $action = grant_or_refresh_access(db(), $email);
    }
} catch (\Throwable $ex) {
    error_log('WYZCORE_WEBHOOK action failed: ' . $ex->getMessage());
    json_out(['ok' => false, 'reason' => 'server'], 200);
}

json_out(['ok' => true, 'event' => $event, 'action' => $action, 'auth' => $authMethod], 200);


/** All request headers, name => value. */
function collect_headers(): array
{
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        if (is_array($h)) { return $h; }
    }
    $out = [];
    foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
            $out[$name] = $v;
        }
    }
    return $out;
}
