<?php
/**
 * ratelimit.php — application-level rate limiting (shared hosting has no
 * server-level equivalent). Technical Spec section 6.
 *
 * Default policy: 5 attempts per 15 minutes, then a 30 minute cooldown.
 * Enforced per hashed IP and per hashed email, whichever trips first.
 */

declare(strict_types=1);

/**
 * Returns true if the action is allowed and records the attempt.
 * Returns false if the identifier is currently locked out.
 *
 * @param string $identifier already hashed (see rl_identifier())
 * @param string $action      e.g. 'verify-code', 'login'
 */
function rate_limit_hit(
    string $identifier,
    string $action,
    int $max = 5,
    int $windowMinutes = 15,
    int $cooldownMinutes = 30
): bool {
    $pdo = db();
    $now = new DateTimeImmutable('now', new DateTimeZone('+08:00'));
    $nowStr = $now->format('Y-m-d H:i:s');

    $sel = $pdo->prepare(
        'SELECT attempts, window_start, locked_until
           FROM rate_limits WHERE identifier = ? AND action = ? LIMIT 1'
    );
    $sel->execute([$identifier, $action]);
    $row = $sel->fetch();

    // Currently locked out?
    if ($row && $row['locked_until'] !== null && $row['locked_until'] > $nowStr) {
        return false;
    }

    if (!$row) {
        $ins = $pdo->prepare(
            'INSERT INTO rate_limits (identifier, action, attempts, window_start)
             VALUES (?, ?, 1, ?)'
        );
        $ins->execute([$identifier, $action, $nowStr]);
        return true;
    }

    $windowStart = new DateTimeImmutable($row['window_start'], new DateTimeZone('+08:00'));
    $windowAgeMin = ($now->getTimestamp() - $windowStart->getTimestamp()) / 60;

    // Window expired (or cooldown finished): reset to a fresh window.
    if ($windowAgeMin >= $windowMinutes) {
        $upd = $pdo->prepare(
            'UPDATE rate_limits
                SET attempts = 1, window_start = ?, locked_until = NULL
              WHERE identifier = ? AND action = ?'
        );
        $upd->execute([$nowStr, $identifier, $action]);
        return true;
    }

    // Inside the window: would this attempt exceed the limit?
    $attempts = (int)$row['attempts'] + 1;
    if ($attempts > $max) {
        $lockedUntil = $now->add(new DateInterval('PT' . $cooldownMinutes . 'M'))
                           ->format('Y-m-d H:i:s');
        $upd = $pdo->prepare(
            'UPDATE rate_limits SET attempts = ?, locked_until = ?
              WHERE identifier = ? AND action = ?'
        );
        $upd->execute([$attempts, $lockedUntil, $identifier, $action]);
        return false;
    }

    $upd = $pdo->prepare(
        'UPDATE rate_limits SET attempts = ? WHERE identifier = ? AND action = ?'
    );
    $upd->execute([$attempts, $identifier, $action]);
    return true;
}

/** Clear a limiter after a successful auth, so honest users are not punished. */
function rate_limit_clear(string $identifier, string $action): void
{
    $del = db()->prepare('DELETE FROM rate_limits WHERE identifier = ? AND action = ?');
    $del->execute([$identifier, $action]);
}

/**
 * Convenience: enforce a policy across both IP and email identifiers.
 * Stops with 429 when either is locked.
 */
function rate_limit_guard(string $action, string $email = '', int $max = 5): void
{
    $ipId = rl_identifier('ip:' . client_ip());
    if (!rate_limit_hit($ipId, $action, $max)) {
        fail('Too many attempts. Please wait 30 minutes and try again.', 429);
    }
    if ($email !== '') {
        $emailId = rl_identifier('email:' . normalize_email($email));
        if (!rate_limit_hit($emailId, $action, $max)) {
            fail('Too many attempts. Please wait 30 minutes and try again.', 429);
        }
    }
}
