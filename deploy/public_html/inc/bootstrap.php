<?php
/**
 * bootstrap.php — required first by every page and every API endpoint.
 *
 * Loads config from ABOVE the web root, opens one PDO connection, starts a
 * hardened session, sets baseline security headers, and pulls in the shared
 * helper libraries. Nothing here echoes output.
 */

declare(strict_types=1);

// ── Config lives one level above public_html ────────────────────────────
define('CONFIG_DIR', realpath(__DIR__ . '/../../config'));
if (CONFIG_DIR === false) {
    http_response_code(500);
    exit('Server misconfigured: config directory not found.');
}

$APP     = require CONFIG_DIR . '/app.php';
$SECRETS = require CONFIG_DIR . '/secrets.php';

define('APP', $APP);
define('WEB_ROOT', realpath(__DIR__ . '/..'));   // public_html, for asset versioning
define('IS_PROD', ($APP['environment'] ?? 'production') === 'production');

// ── Error handling: verbose off in production, always logged ─────────────
error_reporting(E_ALL);
ini_set('display_errors', IS_PROD ? '0' : '1');
ini_set('log_errors', '1');

// ── Composer autoloader (PHPMailer for email), if bundled ────────────────
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}

// ── One shared PDO connection ────────────────────────────────────────────
require __DIR__ . '/db.php';           // defines db(): PDO

// ── Shared helpers ───────────────────────────────────────────────────────
require __DIR__ . '/helpers.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/ratelimit.php';

// ── Hardened session ─────────────────────────────────────────────────────
// CLI (cron scripts) never need a session; starting one there only warns.
if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    $idleDays = (int)($APP['session_idle_days'] ?? 30);
    $lifetime = $idleDays * 24 * 60 * 60;
    ini_set('session.gc_maxlifetime', (string)$lifetime);
    ini_set('session.use_strict_mode', '1');

    // Secure only makes sense under HTTPS. Detect it, but the site forces
    // HTTPS in .htaccess, so this is Secure in practice.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_name('wyztoolkit');
    session_start();
}

// ── A per-request CSP nonce for the few inline module scripts on pages ───
if (empty($GLOBALS['CSP_NONCE'])) {
    $GLOBALS['CSP_NONCE'] = base64_encode(random_bytes(16));
}

// ── Baseline security headers (both pages and API responses) ─────────────
// The widget host and Google Fonts are the only third parties allowed.
if (!headers_sent()) {
    $nonce = $GLOBALS['CSP_NONCE'];
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'nonce-$nonce' https://agents.wyzquestpro.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com",
        "img-src 'self' data:",
        "connect-src 'self' https://agents.wyzquestpro.com",
        "frame-src https://agents.wyzquestpro.com",
        "base-uri 'self'",
        "form-action 'self'",
        "object-src 'none'",
    ]);
    header('Content-Security-Policy: ' . $csp);
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 0');   // modern browsers rely on CSP, not the legacy auditor
}
