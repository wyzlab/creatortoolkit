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

return [
    // Peppers the access-code HMAC (Technical Spec 2.1). SET ONCE, NEVER CHANGE.
    'code_pepper' => 'REPLACE_WITH_64_HEX_CHARS_CODE_PEPPER',   // PLACEHOLDER

    // Salts password-reset tokens before storage.
    'csrf_salt'   => 'REPLACE_WITH_64_HEX_CHARS_CSRF_SALT',     // PLACEHOLDER

    // Peppers the identifiers written to the rate_limits table so raw IPs
    // are never stored in plaintext.
    'ip_pepper'   => 'REPLACE_WITH_64_HEX_CHARS_IP_PEPPER',     // PLACEHOLDER
];
