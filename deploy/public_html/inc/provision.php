<?php
/**
 * provision.php — create a ready-to-use learner account for an email, without
 * a code and without a password yet. Used by the purchase webhook so a buyer
 * gets access automatically. They receive a set-password link by email.
 */

declare(strict_types=1);

require_once __DIR__ . '/tools.php';
require_once __DIR__ . '/wyzai.php';

/**
 * Create the account, seed the profile, unlock Gate 1, claim the Welcome
 * Buddy code. Returns the new user id. Caller wraps this in a transaction.
 * Assumes the email is valid and not already a user.
 */
function provision_learner(PDO $pdo, string $email): int
{
    $now = now_dt();

    $pdo->prepare('INSERT INTO users (email, password_hash, role, status, created_at)
                   VALUES (?, NULL, "learner", "active", ?)')
        ->execute([$email, $now]);
    $userId = (int)$pdo->lastInsertId();

    $pdo->prepare('INSERT INTO user_profile (user_id, profile_json, version, updated_at)
                   VALUES (?, "{}", 1, ?)')
        ->execute([$userId, $now]);

    $insGate = $pdo->prepare('INSERT INTO gate_progress (user_id, gate_number, tools_required, tools_completed, unlocked_at)
                              VALUES (?, ?, ?, 0, ?)');
    foreach (GATES as $n => $g) {
        $insGate->execute([$userId, $n, $g['tools_required'], $n === 1 ? $now : null]);
    }

    wyzai_claim($pdo, $userId, 'login');
    return $userId;
}

/**
 * Create a single-use set-password link for a user and return the full URL.
 * Reuses the password_resets mechanism (60 minute expiry).
 */
function make_set_password_link(PDO $pdo, int $userId): string
{
    $secrets = require CONFIG_DIR . '/secrets.php';
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash_hmac('sha256', $token, $secrets['csrf_salt']);
    $expires = (new DateTimeImmutable('now', new DateTimeZone('+08:00')))
        ->add(new DateInterval('PT60M'))->format('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
                   VALUES (?, ?, ?, ?)')
        ->execute([$userId, $tokenHash, $expires, now_dt()]);
    return APP['app_url'] . '/reset.php?token=' . $token;
}

/**
 * Grant access to an email automatically (purchase flow). Idempotent.
 * - new email: create the account and email a set-password link.
 * - exists, no password yet: email a fresh set-password link.
 * - exists, has password: nothing to do.
 * Returns 'provisioned' | 're_sent' | 'already_active'.
 */
function grant_or_refresh_access(PDO $pdo, string $email): string
{
    require_once __DIR__ . '/mailer.php';

    $u = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
    $u->execute([$email]);
    $row = $u->fetch();

    if ($row && $row['password_hash'] !== null) {
        // Reactivate if they were suspended (e.g. a cancel then a renew).
        $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([(int)$row['id']]);
        return 'already_active';
    }

    if ($row) {
        $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([(int)$row['id']]);
        $link = make_set_password_link($pdo, (int)$row['id']);
        send_setpw_email($pdo, $email, $link, (int)$row['id']);
        return 're_sent';
    }

    $pdo->beginTransaction();
    try {
        $userId = provision_learner($pdo, $email);
        $link = make_set_password_link($pdo, $userId);
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    send_setpw_email($pdo, $email, $link, $userId);
    return 'provisioned';
}

/** Suspend an account (cancel order). Keeps their data; just blocks sign-in. */
function suspend_access(PDO $pdo, string $email): bool
{
    $stmt = $pdo->prepare("UPDATE users SET status='suspended' WHERE email=?");
    $stmt->execute([$email]);
    return $stmt->rowCount() > 0;
}

/** The set-password ("you're in") email. */
function send_setpw_email(PDO $pdo, string $email, string $link, int $userId): void
{
    $subject = 'Set up your DIY Creator Starter Toolkit';
    $html = '<div style="font-family:Inter,Arial,sans-serif;max-width:560px">'
        . '<h1 style="font-family:Montserrat,Arial,sans-serif">You are in.</h1>'
        . '<p>Thank you for your purchase. Your DIY Creator Starter Toolkit is ready.</p>'
        . '<p><a href="' . e($link) . '">Click here to set your password and start.</a></p>'
        . '<p>This link works once, within 60 minutes. If it expires, use "Forgot your password" on the login page.</p>'
        . '</div>';
    $text = "You are in.\n\nThank you for your purchase. Your DIY Creator Starter Toolkit is ready.\n\n"
        . "Set your password and start: $link\n\nThis link works once, within 60 minutes.\n";
    mail_queue('purchase_access', $email, $subject, $html, $text, $userId);
}
