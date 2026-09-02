<?php
/**
 * helpers.php — small shared utilities used across pages and endpoints.
 */

declare(strict_types=1);

/** Escape for safe HTML output. Use everywhere user text meets markup. */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Current timestamp in the app timezone, MySQL DATETIME format. */
function now_dt(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('+08:00')))->format('Y-m-d H:i:s');
}

/** Read and decode a JSON request body. Returns [] on empty or bad JSON. */
function json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Send a JSON response and stop. */
function json_out(array $data, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Send a JSON error and stop. Message is safe for display. */
function fail(string $message, int $status = 400, array $extra = []): void
{
    json_out(array_merge(['ok' => false, 'error' => $message], $extra), $status);
}

/** Require a POST request or stop with 405. */
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        fail('Method not allowed.', 405);
    }
}

/** Basic email sanity check. */
function is_email(string $email): bool
{
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 190;
}

/** Trim + lowercase an email for consistent storage and lookup. */
function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

/**
 * Normalize an access code for lookup: uppercase, strip spaces and hyphens.
 * So "0-h3g-qks" and "0H3GQKS" resolve to the same code. Technical Spec 2.1.
 */
function normalize_code(string $code): string
{
    return strtoupper(preg_replace('/[\s\-]+/', '', $code) ?? '');
}

/** Deterministic HMAC lookup value for an access code. */
function code_lookup(string $code): string
{
    $secrets = require CONFIG_DIR . '/secrets.php';
    return hash_hmac('sha256', normalize_code($code), $secrets['code_pepper']);
}

/** A privacy-preserving identifier for rate limiting (hashed ip or email). */
function rl_identifier(string $value): string
{
    $secrets = require CONFIG_DIR . '/secrets.php';
    return substr(hash_hmac('sha256', $value, $secrets['ip_pepper']), 0, 64);
}

/** Best-effort client IP. */
function client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** Redirect helper for page controllers. */
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/**
 * A local asset URL with a cache-busting version from the file's mtime, so a
 * changed CSS/JS file is always re-fetched (browsers cache these for a week).
 */
function asset(string $path): string
{
    $full = (defined('WEB_ROOT') ? WEB_ROOT : '') . $path;
    $v = @filemtime($full);
    return $v ? ($path . '?v=' . $v) : $path;
}
