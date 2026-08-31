<?php
/**
 * POST /api/grant-access.php   ->   {ok, result}
 *
 * A simple, generic purchase webhook: create the buyer's account and email a
 * set-password link. Protected by a shared secret header  X-Grant-Secret.
 * For the wyzcore-specific webhook (signed payloads, sale/renew/cancel), see
 * wyzcore-webhook.php.
 *
 * Input JSON: { "email": "buyer@example.com" }
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/provision.php';

require_post();

$secrets = require CONFIG_DIR . '/secrets.php';
$expected = (string)($secrets['grant_secret'] ?? '');
$got = (string)($_SERVER['HTTP_X_GRANT_SECRET'] ?? '');
if ($expected === '' || strpos($expected, 'REPLACE') === 0 || !hash_equals($expected, $got)) {
    fail('Unauthorized.', 401);
}

rate_limit_guard('grant-access', '', 60);

$in = json_input();
$email = normalize_email((string)($in['email'] ?? ''));
if (!is_email($email)) {
    fail('A valid email is required.', 422);
}

try {
    $result = grant_or_refresh_access(db(), $email);
} catch (\Throwable $ex) {
    error_log('grant-access failed: ' . $ex->getMessage());
    fail('Could not provision access right now.', 500);
}

json_out(['ok' => true, 'result' => $result]);
