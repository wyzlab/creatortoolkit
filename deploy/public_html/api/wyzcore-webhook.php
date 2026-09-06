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

/** Pull an email out of a payload, trying common shapes. */
function find_email(array $p): string
{
    $keys = ['email', 'customer_email', 'buyer_email', 'user_email', 'contact_email'];
    foreach ($keys as $k) {
        if (!empty($p[$k]) && is_string($p[$k])) { return normalize_email($p[$k]); }
    }
    foreach (['customer', 'buyer', 'user', 'data', 'order', 'contact'] as $group) {
        if (isset($p[$group]) && is_array($p[$group])) {
            $found = find_email($p[$group]);
            if ($found !== '') { return $found; }
        }
    }
    return '';
}

/** Pull an event/type string out of a payload. */
function find_event(array $p): string
{
    foreach (['event', 'type', 'event_type', 'topic', 'action', 'status'] as $k) {
        if (!empty($p[$k]) && is_string($p[$k])) { return $p[$k]; }
    }
    if (isset($p['data']) && is_array($p['data'])) { return find_event($p['data']); }
    return '';
}

/**
 * Collect every product/item title in the payload, trying common shapes:
 * a top-level product name, and arrays of line items each with a title/name.
 * Recurses into wrapper groups (data/order/...). Returns a flat list of titles.
 */
function find_products(array $p): array
{
    $titles = [];
    $titleKeys = ['product', 'product_name', 'product_title', 'item', 'item_name',
                  'item_title', 'title', 'name', 'plan', 'plan_name', 'offer', 'offer_name'];
    foreach ($titleKeys as $k) {
        if (!empty($p[$k]) && is_string($p[$k])) { $titles[] = $p[$k]; }
    }
    // Arrays of line items.
    foreach (['line_items', 'items', 'products', 'order_items', 'lines', 'cart'] as $k) {
        if (!empty($p[$k]) && is_array($p[$k])) {
            foreach ($p[$k] as $item) {
                if (is_string($item)) { $titles[] = $item; }
                elseif (is_array($item)) { $titles = array_merge($titles, find_products($item)); }
            }
        }
    }
    // Recurse into common wrapper objects.
    foreach (['data', 'order', 'sale', 'purchase', 'subscription', 'checkout'] as $group) {
        if (isset($p[$group]) && is_array($p[$group])) {
            $titles = array_merge($titles, find_products($p[$group]));
        }
    }
    return array_values(array_unique(array_filter($titles, 'is_string')));
}

/** Collect product ids from the payload (product_id, item id, line-item ids). */
function find_product_ids(array $p): array
{
    $ids = [];
    foreach (['product_id', 'product_code', 'item_id', 'sku', 'id'] as $k) {
        if (isset($p[$k]) && (is_string($p[$k]) || is_int($p[$k]))) { $ids[] = (string)$p[$k]; }
    }
    foreach (['line_items', 'items', 'products', 'order_items', 'lines', 'cart'] as $k) {
        if (!empty($p[$k]) && is_array($p[$k])) {
            foreach ($p[$k] as $item) {
                if (is_array($item)) { $ids = array_merge($ids, find_product_ids($item)); }
            }
        }
    }
    foreach (['data', 'order', 'sale', 'purchase', 'subscription', 'checkout'] as $group) {
        if (isset($p[$group]) && is_array($p[$group])) {
            $ids = array_merge($ids, find_product_ids($p[$group]));
        }
    }
    return array_values(array_unique(array_filter($ids, fn($v) => $v !== '')));
}

/**
 * Does this sale identify the DIY Creator Toolkit? True if any product id
 * matches toolkit_product_ids, OR any product title contains a configured
 * phrase (case-insensitive). If the payload carried NO product titles or ids we
 * do NOT assume it is the toolkit — better to ignore an unrecognised sale than
 * to grant wrongly.
 */
function purchase_is_toolkit(array $titles, array $ids = []): bool
{
    // Product id is the most reliable signal (titles can be renamed/translated).
    $wantIds = array_map('strval', APP['toolkit_product_ids'] ?? []);
    foreach ($ids as $id) {
        if (in_array((string)$id, $wantIds, true)) { return true; }
    }
    if (!$titles) { return false; }
    $needles = APP['toolkit_product_match'] ?? [];
    $needles[] = 'diy-creator-starter-toolkit';
    foreach ($titles as $t) {
        $hay = mb_strtolower((string)$t);
        foreach ($needles as $needle) {
            $needle = mb_strtolower(trim((string)$needle));
            if ($needle !== '' && strpos($hay, $needle) !== false) { return true; }
        }
    }
    return false;
}
