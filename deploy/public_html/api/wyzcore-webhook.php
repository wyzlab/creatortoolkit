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

require_post();

$raw = file_get_contents('php://input') ?: '';
$headers = collect_headers();

// Always log for format discovery. Truncate to keep the log sane.
error_log('WYZCORE_WEBHOOK headers=' . json_encode($headers)
        . ' body=' . substr($raw, 0, 2000));

$secrets = require CONFIG_DIR . '/secrets.php';
$sigToken = (string)($secrets['wyzcore_signature_token'] ?? '');

// Log-only mode until the signature token is set.
if ($sigToken === '' || strpos($sigToken, 'REPLACE') === 0) {
    json_out(['ok' => true, 'mode' => 'log-only, signature token not set'], 200);
}

// Verify the signature. Accept hex or base64 HMAC-SHA256 of the raw body, in
// any of the common signature headers.
$expectedHex = hash_hmac('sha256', $raw, $sigToken);
$expectedB64 = base64_encode(hash_hmac('sha256', $raw, $sigToken, true));
$verified = false;
foreach ($headers as $name => $value) {
    $n = strtolower($name);
    if (strpos($n, 'sign') === false && strpos($n, 'hash') === false) {
        continue;
    }
    $v = trim($value);
    // strip a possible "sha256=" prefix
    $v = preg_replace('/^sha256=/i', '', $v);
    if (hash_equals($expectedHex, $v) || hash_equals($expectedB64, $v)) {
        $verified = true;
        break;
    }
}

if (!$verified) {
    error_log('WYZCORE_WEBHOOK signature did not verify');
    // Ack so the store does not retry forever, but take no action.
    json_out(['ok' => false, 'reason' => 'signature'], 200);
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    json_out(['ok' => false, 'reason' => 'bad json'], 200);
}

$email = find_email($payload);
$event = strtolower(find_event($payload));

if ($email === '' || !is_email($email)) {
    error_log('WYZCORE_WEBHOOK no usable email in payload');
    json_out(['ok' => false, 'reason' => 'no email'], 200);
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

json_out(['ok' => true, 'event' => $event, 'action' => $action], 200);


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
