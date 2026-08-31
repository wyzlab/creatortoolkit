<?php
/**
 * Server-side secrets.  LIVES ABOVE public_html — never web-reachable,
 * never referenced from client JavaScript.
 *
 * Generate each value once with a long random string and DO NOT change it
 * afterwards. Changing CODE_PEPPER invalidates every access code already
 * imported (they were hashed with the old pepper).
 *
 * To generate strong values on the server (SSH) or any PHP prompt:
 *   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
 * Run it twice, paste one into each blank below.
 */

// GIT DEPLOY: put your real values in secrets.local.php (gitignored) so a git
// pull never overwrites them. If that file exists, it is used instead.
$local = __DIR__ . '/secrets.local.php';
if (is_file($local)) {
    return require $local;
}

return [
    // Peppers the access-code HMAC (Technical Spec 2.1). SET ONCE, NEVER CHANGE.
    'code_pepper' => 'REPLACE_WITH_64_HEX_CHARS_CODE_PEPPER',   // PLACEHOLDER

    // Salts password-reset tokens before storage.
    'csrf_salt'   => 'REPLACE_WITH_64_HEX_CHARS_CSRF_SALT',     // PLACEHOLDER

    // Peppers the identifiers written to the rate_limits table so raw IPs
    // are never stored in plaintext.
    'ip_pepper'   => 'REPLACE_WITH_64_HEX_CHARS_IP_PEPPER',     // PLACEHOLDER

    // Shared secret the generic purchase webhook (grant-access.php) requires.
    // Your store sends it as the X-Grant-Secret header. Leave as REPLACE... to
    // keep that webhook disabled until you set it.
    'grant_secret' => 'REPLACE_WITH_A_LONG_RANDOM_GRANT_SECRET',  // PLACEHOLDER

    // wyzcore store webhook. The signature token verifies incoming events; the
    // access token is for calling wyzcore's API if we ever need to. Leave as
    // REPLACE... to keep wyzcore-webhook.php in safe log-only mode.
    'wyzcore_signature_token' => 'REPLACE_WITH_WYZCORE_SIGNATURE_TOKEN',  // PLACEHOLDER
    'wyzcore_access_token'    => 'REPLACE_WITH_WYZCORE_ACCESS_TOKEN',     // PLACEHOLDER
];
