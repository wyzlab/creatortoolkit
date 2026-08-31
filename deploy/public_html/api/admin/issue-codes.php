<?php
/**
 * POST /api/admin/issue-codes.php  {emails, batch_label}
 *   -> {ok, issued:[{email, code}], skipped:[{email, reason}]}
 *
 * Admin only. For each email, mints one code tagged to that buyer and queues
 * an email with the code and a claim link. This is the bulk, no-copy-paste
 * path: paste your buyer emails, and each one gets their own code and message.
 *
 * Until EmailIt is configured (Stage E) the messages are written to email_log
 * instead of sent, and the codes are still returned here so you can distribute
 * them yourself in the meantime.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/guard.php';
require_once __DIR__ . '/../../inc/codes.php';
require_once __DIR__ . '/../../inc/mailer.php';

api_require_admin();
require_post();
csrf_check();

$in    = json_input();
$batch = trim((string)($in['batch_label'] ?? 'purchase')) ?: 'purchase';

// Accept either an array of emails or a newline/comma separated string.
$raw = $in['emails'] ?? [];
if (is_string($raw)) {
    $raw = preg_split('/[\s,;]+/', $raw) ?: [];
}
if (!is_array($raw)) { $raw = []; }

$emails = [];
foreach ($raw as $e) {
    $e = normalize_email((string)$e);
    if ($e !== '') { $emails[$e] = true; }   // de-duplicate
}
$emails = array_keys($emails);

if (!$emails) {
    fail('Add at least one email address.');
}
if (count($emails) > 500) {
    fail('Please issue to at most 500 emails at a time.');
}

$pdo = db();
$issued = [];
$skipped = [];

foreach ($emails as $email) {
    if (!is_email($email)) {
        $skipped[] = ['email' => $email, 'reason' => 'not a valid email'];
        continue;
    }
    // If they already have an account, they do not need a new code.
    $u = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $u->execute([$email]);
    if ($u->fetch()) {
        $skipped[] = ['email' => $email, 'reason' => 'already has an account'];
        continue;
    }

    $codes = mint_codes($pdo, 1, $batch, $email);
    if (!$codes) {
        $skipped[] = ['email' => $email, 'reason' => 'could not mint a code'];
        continue;
    }
    $code = $codes[0];
    queue_access_email($pdo, $email, $code);
    $issued[] = ['email' => $email, 'code' => $code];
}

json_out(['ok' => true, 'issued' => $issued, 'skipped' => $skipped, 'batch_label' => $batch]);


/** Queue the "here is your access" email with the code and a claim link. */
function queue_access_email(PDO $pdo, string $email, string $code): void
{
    $url = APP['app_url'];
    $subject = 'Your access to the DIY Creator Starter Toolkit';
    $html = '<div style="font-family:Inter,Arial,sans-serif;max-width:560px">'
        . '<h1 style="font-family:Montserrat,Arial,sans-serif">You are in.</h1>'
        . '<p>Thank you. Here is your access to the DIY Creator Starter Toolkit.</p>'
        . '<p>Your access code is <strong>' . e($code) . '</strong>.</p>'
        . '<p>To start: go to <a href="' . e($url) . '">' . e($url) . '</a>, enter this email and your code, and choose a password.</p>'
        . '<p>Your code works once. After that you sign in with your email and password.</p>'
        . '</div>';
    $text = "You are in.\n\nHere is your access to the DIY Creator Starter Toolkit.\n\n"
        . "Your access code is: $code\n\n"
        . "To start: go to $url , enter this email and your code, and choose a password.\n"
        . "Your code works once. After that you sign in with your email and password.\n";
    mail_queue('access_code', $email, $subject, $html, $text, null);
}
