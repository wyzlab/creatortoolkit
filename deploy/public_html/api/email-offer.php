<?php
/**
 * POST /api/email-offer.php  {id}  ->  {ok, status, message, error}
 * Emails one of the learner's saved offers to their own account email. The body
 * is the same offer card shown on screen, wrapped in the branded email shell.
 * Reports whether it actually sent (or was only queued because mail is off).
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';
require_once __DIR__ . '/../inc/offers.php';
require_once __DIR__ . '/../inc/mailer.php';

$user = api_require_login();
$uid  = (int)$user['id'];
$to   = (string)$user['email'];

require_post();
csrf_check();

$in = json_input();
$id = (int)($in['id'] ?? 0);

$offer = $id > 0 ? get_offer(db(), $uid, $id) : null;
if (!$offer) { fail('That offer could not be found.', 404); }

$title = (string)$offer['title'];
$saved = date('F j, Y', strtotime((string)$offer['created_at']));

// Build the email body: a short header, then the offer card exactly as saved
// (result_html was built server-side from a whitelist, so it is safe to embed).
$html = '<div style="font-family:Inter,Arial,Helvetica,sans-serif;color:#1f2a44;">'
      . '<p style="margin:0 0 4px;color:#64748b;font-size:13px;letter-spacing:0.04em;text-transform:uppercase;">'
      . e(APP['academy_line']) . '</p>'
      . '<h1 style="margin:0 0 4px;font-size:22px;color:#0f2350;">' . e($title) . '</h1>'
      . '<p style="margin:0 0 20px;color:#64748b;font-size:13px;">Saved ' . e($saved) . '</p>'
      . $offer['result_html']
      . '</div>';

$text = $title . "\n" . APP['academy_line'] . " — saved " . $saved . "\n\n"
      . trim(preg_replace('/\n{3,}/', "\n\n", strip_tags(str_replace(['</p>', '</li>', '<br>', '<br/>'], "\n", (string)$offer['result_html']))));

$logId = mail_queue('offer_copy', $to, 'Your offer: ' . $title, $html, $text, $uid);

// Read back what happened so the button can say sent vs. queued.
$row = db()->prepare('SELECT status, error FROM email_log WHERE id = ? LIMIT 1');
$row->execute([$logId]);
$r = $row->fetch() ?: ['status' => 'unknown', 'error' => null];

$message = [
    'sent'   => 'Sent. Check ' . $to . ' (including spam) in a minute.',
    'failed' => 'Could not send just now. Please try again in a moment.',
    'queued' => 'Saved to send. Email delivery is not switched on yet, so it has not gone out.',
][$r['status']] ?? 'Unknown result.';

json_out(['ok' => $r['status'] === 'sent', 'status' => $r['status'], 'to' => $to,
    'message' => $message, 'error' => $r['error']]);
