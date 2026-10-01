<?php
/**
 * POST /api/admin/set-mail-config.php
 *   { enabled, host, port, encryption, username, password?, from_email, from_name, reply_to? }
 *   -> { ok, ...status }
 * Admin only. Stores the SMTP settings in app_settings (auto-creating the table)
 * so the email provider can be configured from the browser without filesystem
 * access. A blank password leaves the stored one unchanged. The password is
 * never returned.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/guard.php';
require_once __DIR__ . '/../../inc/settings.php';

api_require_admin();
require_post();
csrf_check();

$in         = json_input();
$enabled    = !empty($in['enabled']);
$host       = trim((string)($in['host'] ?? ''));
$port       = (int)($in['port'] ?? 0);
$encryption = strtolower(trim((string)($in['encryption'] ?? 'tls')));
$username   = trim((string)($in['username'] ?? ''));
$password   = (string)($in['password'] ?? '');   // blank = keep existing
$fromEmail  = normalize_email((string)($in['from_email'] ?? ''));
$fromName   = trim((string)($in['from_name'] ?? ''));
$replyTo    = normalize_email((string)($in['reply_to'] ?? ''));

// Validate (stricter when turning sending ON).
if (!in_array($encryption, ['tls', 'ssl'], true)) {
    fail('Encryption must be "tls" (port 587) or "ssl" (port 465).');
}
if ($host !== '' && (strlen($host) > 190 || !preg_match('/^[A-Za-z0-9.\-]+$/', $host))) {
    fail('That SMTP host does not look right.');
}
if ($port !== 0 && ($port < 1 || $port > 65535)) {
    fail('Port must be between 1 and 65535 (EmailIt uses 587).');
}
if ($fromEmail !== '' && !is_email($fromEmail)) {
    fail('The "from" address is not a valid email.');
}
if ($replyTo !== '' && !is_email($replyTo)) {
    fail('The reply-to address is not a valid email.');
}
if ($enabled) {
    if ($host === '' || $username === '' || $fromEmail === '') {
        fail('To switch sending on, set the host, username and from-address first.');
    }
    // Require a password either newly provided or already stored.
    $havePw = $password !== '' || token_is_set(setting_get(db(), 'mail_password'));
    if (!$havePw) {
        fail('To switch sending on, enter the SMTP password/credential.');
    }
}

try {
    $pairs = [
        'mail_enabled'    => $enabled ? '1' : '0',
        'mail_host'       => $host,
        'mail_port'       => $port > 0 ? (string)$port : '',
        'mail_encryption' => $encryption,
        'mail_username'   => $username,
        'mail_from_email' => $fromEmail,
        'mail_from_name'  => $fromName,
        'mail_reply_to'   => $replyTo,
    ];
    foreach ($pairs as $k => $v) {
        setting_set(db(), $k, $v);
    }
    if ($password !== '') {
        setting_set(db(), 'mail_password', $password);
    }
} catch (\Throwable $e) {
    fail($e->getMessage(), 500);
}

// Report the new effective status (no password).
require_once __DIR__ . '/../../inc/mailer.php';
$cfg  = mail_config();
$h    = strtolower((string)($cfg['host'] ?? ''));
$provider = strpos($h, 'emailit') !== false ? 'EmailIt'
          : (strpos($h, 'hostinger') !== false ? 'Hostinger'
          : ((string)($cfg['host'] ?? '') ?: 'not configured'));

json_out([
    'ok'       => true,
    'enabled'  => (bool)($cfg['enabled'] ?? false),
    'provider' => $provider,
    'host'     => (string)($cfg['host'] ?? ''),
    'port'     => (int)($cfg['port'] ?? 0),
]);
