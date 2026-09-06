<?php
/**
 * send-claim-reminders.php — the delayed set-password ("you're in") email.
 *
 * Buyers finish on the thank-you page, so we do NOT email at purchase time.
 * This script, run on a schedule (Hostinger cron), emails the set-password link
 * to any buyer whose account is still UNCLAIMED (no password set) more than
 * app.php 'claim_reminder_delay_minutes' after it was created — and only once.
 *
 * Run from the command line only (it lives above public_html):
 *   php tools/send-claim-reminders.php            # send due reminders
 *   php tools/send-claim-reminders.php --dry-run  # list who is due, send nothing
 *
 * Hostinger cron (every 10 minutes), adjust the path to your account:
 *   *\/10 * * * * php /home/USER/domains/toolkit.wyzcore.com/private/tools/send-claim-reminders.php >> /home/USER/claim-reminders.log 2>&1
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this from the command line only.\n");
}

require_once __DIR__ . '/../public_html/inc/bootstrap.php';
require_once __DIR__ . '/../public_html/inc/provision.php';
require_once __DIR__ . '/../public_html/inc/mailer.php';

$dryRun = in_array('--dry-run', $argv, true);
$delay  = max(1, (int)(APP['claim_reminder_delay_minutes'] ?? 30));
$cutoff = (new DateTimeImmutable('now', new DateTimeZone('+08:00')))
    ->sub(new DateInterval('PT' . $delay . 'M'))->format('Y-m-d H:i:s');

$pdo = db();

// Unclaimed, active accounts (only the purchase flow leaves password_hash NULL)
// that are older than the delay and have not been sent a set-password email.
$sql = "SELECT u.id, u.email
          FROM users u
         WHERE u.status = 'active'
           AND u.password_hash IS NULL
           AND u.created_at <= :cutoff
           AND NOT EXISTS (
                 SELECT 1 FROM email_log e
                  WHERE e.user_id = u.id
                    AND e.email_type = 'purchase_access'
               )
         ORDER BY u.id ASC
         LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute([':cutoff' => $cutoff]);
$due = $stmt->fetchAll();

$stamp = (new DateTimeImmutable('now', new DateTimeZone('+08:00')))->format('Y-m-d H:i:s');
echo "[$stamp] claim reminders: " . count($due) . " account(s) due (unclaimed > {$delay} min).\n";

$sent = 0;
foreach ($due as $r) {
    $uid   = (int)$r['id'];
    $email = (string)$r['email'];
    if ($dryRun) {
        echo "  would email: $email (user $uid)\n";
        continue;
    }
    try {
        $link = make_set_password_link($pdo, $uid);
        send_setpw_email($pdo, $email, $link, $uid);   // logs a 'purchase_access' row => sent once
        $sent++;
        echo "  emailed: $email\n";
    } catch (\Throwable $e) {
        error_log('send-claim-reminders failed for ' . $email . ': ' . $e->getMessage());
        echo "  ERROR emailing $email: " . $e->getMessage() . "\n";
    }
}

if (!$dryRun) {
    echo "[$stamp] done. sent {$sent}.\n";
}
