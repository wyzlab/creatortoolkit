<?php
/**
 * POST /api/admin/send-test-email.php  {to?}  ->  {ok, status, error}
 * Admin only. Sends a test email (to the admin, or a given address) and reports
 * back whether it actually sent, so you can confirm your Hostinger email setup.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/guard.php';
require_once __DIR__ . '/../../inc/mailer.php';

$admin = api_require_admin();
require_post();
csrf_check();

$in = json_input();
$to = normalize_email((string)($in['to'] ?? '')) ?: (string)$admin['email'];
if (!is_email($to)) {
    fail('Enter a valid email address to test.');
}

$html = '<div style="font-family:Inter,Arial,sans-serif"><h2>It works.</h2>'
      . '<p>This is a test from your DIY Creator Starter Toolkit. If you are reading this, email sending is set up correctly.</p></div>';
$text = "It works.\n\nThis is a test from your DIY Creator Starter Toolkit. "
      . "If you are reading this, email sending is set up correctly.\n";

$id = mail_queue('test', $to, 'DIY Creator Toolkit test email', $html, $text, (int)$admin['id']);

// Read back what happened.
$row = db()->prepare('SELECT status, error FROM email_log WHERE id = ? LIMIT 1');
$row->execute([$id]);
$r = $row->fetch() ?: ['status' => 'unknown', 'error' => null];

$message = [
    'sent'   => 'Sent. Check ' . $to . ' (including spam) in a minute.',
    'failed' => 'Could not send. Check your mail settings. Details below.',
    'queued' => 'Logged but not sent. Email is still turned off (mail.local.php enabled=false or missing).',
][$r['status']] ?? 'Unknown result.';

json_out(['ok' => $r['status'] === 'sent', 'status' => $r['status'], 'to' => $to,
    'message' => $message, 'error' => $r['error']]);
