<?php
/**
 * csrf.php — per-session CSRF token, checked on every state-changing POST.
 * The token is exposed to the page as a <meta> tag (see head.php) and sent
 * back by the client as the X-CSRF-Token request header.
 */

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Read the token the client sent, from header first, then POST field. */
function csrf_supplied(): string
{
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($header !== '') {
        return $header;
    }
    return (string)($_POST['csrf_token'] ?? '');
}

/** Verify the CSRF token; stop with 419 on mismatch. Call in every POST endpoint. */
function csrf_check(): void
{
    $sent = csrf_supplied();
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        fail('Your session expired. Please refresh the page and try again.', 419);
    }
}
