<?php
/**
 * mailer.php — every send writes to email_log BEFORE it is attempted, so a
 * failure is visible rather than silent (Technical Spec 7).
 *
 * Stage A/B/C/D: config/mail.php has 'enabled' => false, so this only logs a
 * queued row and returns. Stage E flips 'enabled' true and wires PHPMailer.
 * HTML plus plain text on every send.
 */

declare(strict_types=1);

/**
 * Queue an email (and send it if the mailer is enabled and PHPMailer is
 * present). Returns the email_log row id.
 */
function mail_queue(
    string $type,
    string $toAddress,
    string $subject,
    string $html,
    string $text,
    ?int $userId = null
): int {
    $pdo = db();
    $ins = $pdo->prepare(
        'INSERT INTO email_log (user_id, email_type, to_address, subject, status, attempts, created_at)
         VALUES (?, ?, ?, ?, ?, 0, ?)'
    );
    $ins->execute([$userId, $type, $toAddress, $subject, 'queued', now_dt()]);
    $id = (int)$pdo->lastInsertId();

    $cfg = require CONFIG_DIR . '/mail.php';
    if (empty($cfg['enabled'])) {
        // Deliberately not sending yet. The queued row is the record.
        return $id;
    }

    // ── Stage E: real send via PHPMailer over EmailIt SMTP ───────────────
    // Requires PHPMailer on the include path (Composer or manual). Guarded so
    // an incomplete install degrades to "queued", never a fatal error.
    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        mail_mark($id, 'failed', 'PHPMailer not installed.');
        return $id;
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->Port       = (int)$cfg['port'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['username'];
        $mail->Password   = $cfg['password'];
        $mail->SMTPSecure = $cfg['encryption'];
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addReplyTo($cfg['reply_to']);
        $mail->addAddress($toAddress);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $html;
        $mail->AltBody = $text;
        $mail->send();
        mail_mark($id, 'sent', null);
    } catch (\Throwable $e) {
        mail_mark($id, 'failed', $e->getMessage());
    }
    return $id;
}

/** Update an email_log row's status. */
function mail_mark(int $id, string $status, ?string $error): void
{
    $upd = db()->prepare(
        'UPDATE email_log
            SET status = ?, attempts = attempts + 1,
                sent_at = ?, error = ?
          WHERE id = ?'
    );
    $sentAt = $status === 'sent' ? now_dt() : null;
    $upd->execute([$status, $sentAt, $error, $id]);
}
