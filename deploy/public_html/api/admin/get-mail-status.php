<?php
/**
 * GET /api/admin/get-mail-status.php
 *   -> { enabled, configured, provider, host, port, encryption, username,
 *        from, from_name, reply_to, password_set }
 * Admin only. Reports the EFFECTIVE mail config (file overlaid with admin
 * settings) so the console can show the live provider and prefill the form.
 * NEVER returns the SMTP password — only whether one is set.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/guard.php';
require_once __DIR__ . '/../../inc/mailer.php';

api_require_admin();

$cfg  = mail_config();
$host = (string)($cfg['host'] ?? '');
$user = (string)($cfg['username'] ?? '');
$pass = (string)($cfg['password'] ?? '');

$isPlaceholder = fn(string $v) => $v === '' || strpos($v, 'REPLACE') === 0;
$configured = !$isPlaceholder($host) && !$isPlaceholder($user) && !$isPlaceholder($pass);

$h = strtolower($host);
if (strpos($h, 'hostinger') !== false)   { $provider = 'Hostinger'; }
elseif (strpos($h, 'emailit') !== false) { $provider = 'EmailIt'; }
elseif (strpos($h, 'gmail') !== false || strpos($h, 'google') !== false) { $provider = 'Google'; }
elseif ($configured)                     { $provider = $host; }
else                                     { $provider = 'not configured'; }

json_out([
    'enabled'      => (bool)($cfg['enabled'] ?? false),
    'configured'   => $configured,
    'provider'     => $provider,
    'host'         => $isPlaceholder($host) ? '' : $host,
    'port'         => (int)($cfg['port'] ?? 0),
    'encryption'   => (string)($cfg['encryption'] ?? 'tls'),
    'username'     => $isPlaceholder($user) ? '' : $user,
    'from'         => (string)($cfg['from_email'] ?? ''),
    'from_name'    => (string)($cfg['from_name'] ?? ''),
    'reply_to'     => (string)($cfg['reply_to'] ?? ''),
    'password_set' => !$isPlaceholder($pass),
]);
