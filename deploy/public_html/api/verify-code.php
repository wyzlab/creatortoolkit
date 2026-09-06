<?php
/**
 * POST /api/verify-code.php  {email, code}  ->  {valid, needs_password}
 *
 * The first step of claiming a toolkit. Rate limited, timing-safe, and it
 * returns the SAME generic result for a bad code and a bad email, so it
 * cannot be used to enumerate buyers. Technical Spec 4.1.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/codes.php';

require_post();
csrf_check();

$in    = json_input();
$email = normalize_email((string)($in['email'] ?? ''));
$code  = (string)($in['code'] ?? '');

rate_limit_guard('verify-code', $email, 5);

// Uniform invalid response. Never say which of the two was wrong.
$invalid = fn() => json_out(['valid' => false, 'needs_password' => false,
    'error' => 'That email and code did not match an available toolkit. Please check both and try again.'], 200);

if (!is_email($email) || $code === '') {
    $invalid();
}

$lookup = code_lookup($code);
$stmt = db()->prepare(
    'SELECT id, status, expires_at, batch_label FROM access_codes WHERE code_lookup = ? LIMIT 1'
);
$stmt->execute([$lookup]);
$row = $stmt->fetch();

if (!$row) {
    $invalid();
}
if (in_array($row['status'], ['revoked', 'expired'], true)) {
    $invalid();
}
if ($row['expires_at'] !== null && $row['expires_at'] < now_dt()) {
    $invalid();
}

// A capped universal code that is full: say so, rather than a generic invalid.
if ($row['batch_label'] === '__universal__' && access_codes_slots_supported(db())) {
    $mx = db()->prepare('SELECT max_uses FROM access_codes WHERE id = ? LIMIT 1');
    $mx->execute([(int)$row['id']]);
    $cap = $mx->fetchColumn();
    if (!empty($cap) && code_use_count(db(), (int)$row['id']) >= (int)$cap) {
        json_out(['valid' => false, 'needs_password' => false,
            'error' => 'This code has reached its limit. Please ask the host for a new code.'], 200);
    }
}

// If this email already has an account, they should log in, not claim.
$u = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$u->execute([$email]);
if ($u->fetch()) {
    json_out(['valid' => true, 'needs_password' => false,
        'message' => 'You already have an account with this email. Please log in.'], 200);
}

// A code that is already claimed by someone else: still show the login path,
// without confirming who claimed it.
if ($row['status'] === 'claimed') {
    json_out(['valid' => true, 'needs_password' => false,
        'message' => 'This code is already set up. Please log in with your email and password.'], 200);
}

// Valid, unclaimed, unexpired: proceed to set a password.
json_out(['valid' => true, 'needs_password' => true], 200);
