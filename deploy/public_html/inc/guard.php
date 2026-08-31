<?php
/**
 * guard.php — the server-side access layer. Every gate and tool page opens
 * with require_login() and, where a gate is involved, require_gate($n).
 * Every API endpoint re-checks independently. Two layers, because one is
 * not enough (Technical Spec 2.2 and 5).
 *
 * A page that includes this file must have already required bootstrap.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/tools.php';

/** The logged-in user row, or null. Cached per request. */
function current_user(): ?array
{
    static $user = null;
    static $looked = false;
    if ($looked) {
        return $user;
    }
    $looked = true;

    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, email, role, status FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    if (!$row || $row['status'] !== 'active') {
        return null;
    }
    $user = $row;
    return $user;
}

/** True when someone is logged in with an active account. */
function is_logged_in(): bool
{
    return current_user() !== null;
}

/**
 * Page guard. Redirects to the login page when there is no valid session.
 * For API endpoints use api_require_login() instead (returns JSON 401).
 */
function require_login(): void
{
    if (!is_logged_in()) {
        redirect('/index.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/dashboard.php'));
    }
}

/** API version of the login check: JSON 401 rather than a redirect. */
function api_require_login(): array
{
    $u = current_user();
    if ($u === null) {
        fail('Please log in.', 401);
    }
    return $u;
}

/** Whether a given gate is unlocked for a user (unlocked_at is set). */
function gate_is_unlocked(int $userId, int $gate): bool
{
    if ($gate === 1) {
        // Gate 1 unlocks at account creation, but confirm the row exists.
        $stmt = db()->prepare(
            'SELECT unlocked_at FROM gate_progress WHERE user_id = ? AND gate_number = 1 LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row !== false && $row['unlocked_at'] !== null;
    }
    $stmt = db()->prepare(
        'SELECT unlocked_at FROM gate_progress WHERE user_id = ? AND gate_number = ? LIMIT 1'
    );
    $stmt->execute([$userId, $gate]);
    $row = $stmt->fetch();
    return $row !== false && $row['unlocked_at'] !== null;
}

/**
 * Page guard for a gate. Redirects to the dashboard when the gate is locked,
 * so typing /gate2/... directly returns a redirect, not a page.
 */
function require_gate(int $gate): void
{
    require_login();
    $u = current_user();
    if (!gate_is_unlocked((int)$u['id'], $gate)) {
        redirect('/dashboard.php?locked=' . $gate);
    }
}

/** API version of the gate check: JSON 403. */
function api_require_gate(int $userId, int $gate): void
{
    if (!gate_is_unlocked($userId, $gate)) {
        fail('That step is still locked.', 403);
    }
}

/** Admin page guard. */
function require_admin(): void
{
    require_login();
    $u = current_user();
    if (($u['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Forbidden.');
    }
}

/** API admin guard: JSON 403. */
function api_require_admin(): array
{
    $u = api_require_login();
    if (($u['role'] ?? '') !== 'admin') {
        fail('Forbidden.', 403);
    }
    return $u;
}
