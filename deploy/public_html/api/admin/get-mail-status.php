<?php
/**
 * GET /api/admin/get-mail-status.php -> { enabled, provider, host, port, from, configured }
 * Admin only. Reports which email provider is live (inferred from the SMTP host)
 * so it can be seen from the browser. NEVER returns the SMTP password.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/guard.php';

api_require_admin();

$cfg  = require CONFIG_DIR . '/mail.php';
$host = (string)($cfg['host'] ?? '');
$user = (string)($cfg['username'] ?? '');
$pass = (string)($cfg['password'] ?? '');

// Placeholder creds (shipped defaults) mean nothing real is configured yet.
$configured = $host !== '' && strpos($host, 'REPLACE') !== 0
           && strpos($user, 'REPLACE') !== 0 && strpos($pass, 'REPLACE') !== 0;

$h = strtolower($host);
if (strpos($h, 'hostinger') !== false)      { $provider = 'Hostinger'; }
elseif (strpos($h, 'emailit') !== false)    { $provider = 'EmailIt'; }
elseif (strpos($h, 'gmail') !== false
     || strpos($h, 'google') !== false)     { $provider = 'Google'; }
elseif ($host !== '' && $configured)        { $provider = $host; }
else                                        { $provider = 'not configured'; }

json_out([
    'enabled'    => (bool)($cfg['enabled'] ?? false),
    'configured' => $configured,
    'provider'   => $provider,
    'host'       => $configured ? $host : '',
    'port'       => (int)($cfg['port'] ?? 0),
    'from'       => (string)($cfg['from_email'] ?? ''),
]);
