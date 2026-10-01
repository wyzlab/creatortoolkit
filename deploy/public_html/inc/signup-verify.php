<?php
/**
 * inc/signup-verify.php — email confirmation for universal-code sign-ups.
 *
 * A universal (webinar) code is a shared secret: anyone can type any email with
 * it. To prove the person owns the email before an account exists, we email a
 * one-time link; clicking it authorises the sign-up for that email + code. The
 * table auto-creates on first use so no manual migration is required.
 */

declare(strict_types=1);

const SIGNUP_VERIFY_TTL_MIN = 60;   // link lifetime, minutes
const SIGNUP_SESSION_TTL_MIN = 20;  // confirmed-session lifetime after clicking

/** True if the signup_verifications table exists. Cached per request. */
function signup_verify_supported(PDO $pdo): bool
{
    static $has = null;
    if ($has !== null) { return $has; }
    try {
        $has = (bool)$pdo->query("SHOW TABLES LIKE 'signup_verifications'")->fetchColumn();
    } catch (\Throwable $e) {
        $has = false;
    }
    return $has;
}

/** Create the table if missing. Never throws. Returns true if it exists after. */
function ensure_signup_verify_table(PDO $pdo): bool
{
    if (signup_verify_supported($pdo)) { return true; }
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS signup_verifications (
               id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
               email      VARCHAR(190) NOT NULL,
               code_id    INT UNSIGNED NOT NULL,
               token_hash CHAR(64) NOT NULL,
               expires_at DATETIME NOT NULL,
               used_at    DATETIME NULL,
               created_at DATETIME NOT NULL,
               KEY idx_token (token_hash),
               KEY idx_email (email)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        return (bool)$pdo->query("SHOW TABLES LIKE 'signup_verifications'")->fetchColumn();
    } catch (\Throwable $e) {
        return false;
    }
}

/** Hash a raw token the same way password_resets does (peppered with csrf_salt). */
function signup_token_hash(string $token): string
{
    $secrets = require CONFIG_DIR . '/secrets.php';
    return hash_hmac('sha256', $token, (string)($secrets['csrf_salt'] ?? ''));
}

/**
 * Start an email confirmation: store a one-time token for (email, code_id) and
 * return the full confirmation URL. Any earlier unused tokens for this email are
 * cleared so only the latest link works.
 */
function create_signup_verification(PDO $pdo, string $email, int $codeId): ?string
{
    if (!ensure_signup_verify_table($pdo)) { return null; }
    $pdo->prepare('DELETE FROM signup_verifications WHERE email = ? AND used_at IS NULL')
        ->execute([$email]);
    $token   = bin2hex(random_bytes(32));
    $expires = (new DateTimeImmutable('now', new DateTimeZone('+08:00')))
        ->add(new DateInterval('PT' . SIGNUP_VERIFY_TTL_MIN . 'M'))->format('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO signup_verifications (email, code_id, token_hash, expires_at, created_at)
                   VALUES (?, ?, ?, ?, ?)')
        ->execute([$email, $codeId, signup_token_hash($token), $expires, now_dt()]);
    return rtrim((string)(APP['app_url'] ?? ''), '/') . '/confirm-signup.php?token=' . $token;
}

/**
 * Validate a raw token and mark it used. Returns ['email','code_id'] on success,
 * or null if it is unknown, already used, or expired. One-shot.
 */
function consume_signup_verification(PDO $pdo, string $token): ?array
{
    if ($token === '' || !signup_verify_supported($pdo)) { return null; }
    $st = $pdo->prepare('SELECT id, email, code_id FROM signup_verifications
                          WHERE token_hash = ? AND used_at IS NULL AND expires_at >= ? LIMIT 1');
    $st->execute([signup_token_hash($token), now_dt()]);
    $row = $st->fetch();
    if (!$row) { return null; }
    $pdo->prepare('UPDATE signup_verifications SET used_at = ? WHERE id = ?')
        ->execute([now_dt(), (int)$row['id']]);
    return ['email' => (string)$row['email'], 'code_id' => (int)$row['code_id']];
}

/** Remember, in the session, that this email + code was confirmed just now. */
function mark_signup_confirmed(string $email, int $codeId): void
{
    $_SESSION['signup_confirmed'] = [
        'email'   => $email,
        'code_id' => $codeId,
        'exp'     => time() + SIGNUP_SESSION_TTL_MIN * 60,
    ];
}

/** The confirmed email + code_id if a valid, unexpired confirmation is in session. */
function signup_confirmed_session(): ?array
{
    $c = $_SESSION['signup_confirmed'] ?? null;
    if (!is_array($c) || ($c['exp'] ?? 0) < time()
        || empty($c['email']) || empty($c['code_id'])) {
        return null;
    }
    return ['email' => (string)$c['email'], 'code_id' => (int)$c['code_id']];
}

/** Clear the confirmed-signup session (after the account is created). */
function clear_signup_confirmed(): void
{
    unset($_SESSION['signup_confirmed']);
}

/** The confirmation email body. */
function send_signup_confirm_email(string $email, string $link): void
{
    $subject = 'Confirm your email to open your DIY Creator Starter Toolkit';
    $html = '<div style="font-family:Inter,Arial,sans-serif;max-width:560px">'
        . '<h1 style="font-family:Montserrat,Arial,sans-serif">One quick step</h1>'
        . '<p>Confirm this is your email to finish setting up your DIY Creator Starter Toolkit.</p>'
        . '<p><a href="' . e($link) . '">Confirm my email and set a password</a></p>'
        . '<p>This link works once, within ' . SIGNUP_VERIFY_TTL_MIN . ' minutes. '
        . 'If you did not request this, you can ignore this email.</p>'
        . '</div>';
    $text = "One quick step\n\nConfirm this is your email to finish setting up your "
        . "DIY Creator Starter Toolkit.\n\nConfirm and set a password: $link\n\n"
        . 'This link works once, within ' . SIGNUP_VERIFY_TTL_MIN . " minutes.\n";
    mail_queue('signup_confirm', $email, $subject, $html, $text, null);
}
