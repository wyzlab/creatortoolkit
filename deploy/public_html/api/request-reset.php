<?php
/**
 * POST /api/request-reset.php  {email}  ->  {ok}
 *
 * Always returns ok, whether or not the email exists, so it cannot confirm
 * who has an account. When the email does exist, a single-use reset token is
 * created (60 minute expiry) and the reset email is queued.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/mailer.php';

require_post();
csrf_check();

$in    = json_input();
$email = normalize_email((string)($in['email'] ?? ''));

rate_limit_guard('request-reset', $email, 5);

$ok = ['ok' => true, 'message' => 'If that email has an account, a reset link is on its way.'];

if (!is_email($email)) {
    json_out($ok);   // still generic
}

$stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND status = "active" LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    $secrets = require CONFIG_DIR . '/secrets.php';
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash_hmac('sha256', $token, $secrets['csrf_salt']);
    $expires = (new DateTimeImmutable('now', new DateTimeZone('+08:00')))
        ->add(new DateInterval('PT60M'))->format('Y-m-d H:i:s');

    $ins = db()->prepare(
        'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
         VALUES (?, ?, ?, ?)'
    );
    $ins->execute([(int)$user['id'], $tokenHash, $expires, now_dt()]);

    $link = APP['app_url'] . '/reset.php?token=' . $token;
    $subject = 'Reset your DIY Creator Starter Toolkit password';
    $html = '<div style="font-family:Inter,Arial,sans-serif;max-width:560px">'
        . '<h1 style="font-family:Montserrat,Arial,sans-serif">Reset your password</h1>'
        . '<p>Use the link below within 60 minutes. If you did not ask for this, you can ignore it.</p>'
        . '<p><a href="' . e($link) . '">Choose a new password</a></p></div>';
    $text = "Reset your password\n\nUse this link within 60 minutes:\n$link\n\n"
        . "If you did not ask for this, you can ignore it.\n";
    mail_queue('password_reset', $email, $subject, $html, $text, (int)$user['id']);
}

json_out($ok);
