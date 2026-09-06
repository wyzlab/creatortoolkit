<?php
/**
 * GET /api/admin/webhook-log.php?limit=10 -> { log_present, count, entries:[...] }
 * Admin only. Shows the most recent wyzcore webhook deliveries (from
 * private/webhook-log.txt) with a parsed verdict for each, so the admin can
 * confirm a test sale arrived and mapped correctly — without filesystem access.
 *
 * Token values are redacted from everything returned, so a token wyzcore sends
 * in a header or body is never echoed back to the browser.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/guard.php';
require_once __DIR__ . '/../../inc/settings.php';
require_once __DIR__ . '/../../inc/webhook-util.php';

api_require_admin();

$limit   = max(1, min(30, (int)($_GET['limit'] ?? 10)));
$logFile = __DIR__ . '/../../../private/webhook-log.txt';

if (!is_file($logFile) || !is_readable($logFile)) {
    json_out(['log_present' => false, 'count' => 0, 'entries' => []]);
}

$txt = (string)file_get_contents($logFile);

// Redact the real token values wherever they appear.
$secrets = require CONFIG_DIR . '/secrets.php';
$redactList = array_filter([
    token_setting(db(), 'wyzcore_signature_token', (string)($secrets['wyzcore_signature_token'] ?? '')),
    token_setting(db(), 'wyzcore_access_token',    (string)($secrets['wyzcore_access_token'] ?? '')),
], fn($v) => token_is_set($v));
$redact = function (string $s) use ($redactList): string {
    foreach ($redactList as $tok) { $s = str_replace($tok, '•••', $s); }
    return $s;
};

// Each entry: === <time> ===\nHEADERS: <json>\nBODY: <raw>
preg_match_all('/=== (.*?) ===\nHEADERS: ([^\n]*)\nBODY: (.*?)(?=\n=== |\z)/s', $txt, $m, PREG_SET_ORDER);

// Auth-ish header names we care to surface (names only, never values).
$authish = function (string $n): bool {
    $n = strtolower($n);
    foreach (['sign', 'hash', 'auth', 'token', 'api-key', 'apikey', 'secret'] as $needle) {
        if (strpos($n, $needle) !== false) { return true; }
    }
    return false;
};

$entries = [];
foreach (array_slice($m, -$limit) as $row) {
    $time    = trim($row[1]);
    $headers = json_decode(trim($row[2]), true);
    $bodyRaw = rtrim($row[3]);
    $body    = json_decode($bodyRaw, true);

    $authHeaders = [];
    if (is_array($headers)) {
        foreach ($headers as $name => $_v) { if ($authish((string)$name)) { $authHeaders[] = $name; } }
    }

    $email = $event = ''; $products = $ids = []; $isToolkit = false; $parseOk = is_array($body);
    if ($parseOk) {
        $email     = find_email($body);
        $event     = find_event($body);
        $products  = find_products($body);
        $ids       = find_product_ids($body);
        $isToolkit = purchase_is_toolkit($products, $ids);
    }

    $entries[] = [
        'time'         => $time,
        'auth_headers' => $authHeaders,
        'parsed'       => $parseOk,
        'email'        => $email,
        'event'        => $event,
        'products'     => $products,
        'product_ids'  => $ids,
        'is_toolkit'   => $isToolkit,
        'body'         => $redact(mb_substr($bodyRaw, 0, 1500)),
    ];
}
$entries = array_reverse($entries); // newest first

json_out(['log_present' => true, 'count' => count($entries), 'entries' => $entries]);
