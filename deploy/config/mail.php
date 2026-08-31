<?php
/**
 * EmailIt SMTP credentials for PHPMailer.  LIVES ABOVE public_html.
 *
 * STAGE E. Until 'enabled' is true, the mailer writes every message to the
 * email_log table and does NOT attempt a send. Nothing breaks without this.
 *
 * Fill these from your EmailIt account, then flip 'enabled' to true and run
 * the deliverability test (Gmail, Yahoo, Outlook) described in the Stage E
 * hand-off before you trust it.
 */

// GIT DEPLOY: real SMTP values go in mail.local.php (gitignored).
$local = __DIR__ . '/mail.local.php';
if (is_file($local)) {
    return require $local;
}

return [
    'enabled'    => false,                       // keep false until Stage E creds are in and tested
    'host'       => 'REPLACE_SMTP_HOST',         // PLACEHOLDER — EmailIt SMTP host
    'port'       => 587,                         // PLACEHOLDER — 587 (STARTTLS) or 465 (SMTPS)
    'encryption' => 'tls',                       // PLACEHOLDER — 'tls' for 587, 'ssl' for 465
    'username'   => 'REPLACE_SMTP_USERNAME',     // PLACEHOLDER — EmailIt SMTP username
    'password'   => 'REPLACE_SMTP_PASSWORD',     // PLACEHOLDER — EmailIt SMTP password
    'from_email' => 'hello@wyzcore.com',         // sender address (align SPF/DKIM/DMARC for this domain)
    'from_name'  => 'WyzCore Academy',
    'reply_to'   => 'hello@wyzcore.com',
];
