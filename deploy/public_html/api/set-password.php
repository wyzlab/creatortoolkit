<?php
/**
 * POST /api/set-password.php  {email, code, password}  ->  {ok, redirect}
 *
 * One transaction: create the user, claim the code, seed user_profile,
 * unlock Gate 1 (and create the locked Gate 2 and 3 rows), claim the Welcome
 * Buddy WyzAI code, queue the welcome email. Then log them in.
 * Technical Spec 4.1 and 5.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/tools.php';
require_once __DIR__ . '/../inc/wyzai.php';
require_once __DIR__ . '/../inc/mailer.php';
require_once __DIR__ . '/../inc/codes.php';

require_post();
csrf_check();

$in       = json_input();
$email    = normalize_email((string)($in['email'] ?? ''));
$code     = (string)($in['code'] ?? '');
$password = (string)($in['password'] ?? '');

rate_limit_guard('set-password', $email, 8);

if (!is_email($email)) {
    fail('Please enter a valid email address.');
}
// Minimum 10 characters, no other rules (complexity rules push reuse).
if (strlen($password) < 10) {
    fail('Please choose a password of at least 10 characters.');
}

$pdo = db();
$lookup = code_lookup($code);

$universalJustFilled = null;   // set if this sign-up fills a capped universal code

try {
    $pdo->beginTransaction();

    // Lock the code row and confirm it is still claimable.
    $sel = $pdo->prepare(
        'SELECT id, status, expires_at, batch_label, code_display FROM access_codes WHERE code_lookup = ? FOR UPDATE'
    );
    $sel->execute([$lookup]);
    $codeRow = $sel->fetch();

    // A universal code (batch "__universal__") is shared: it stays unclaimed
    // and many buyers can use it. Revoking it (status revoked) disables it.
    $isUniversal = $codeRow && ($codeRow['batch_label'] === '__universal__');

    if (!$codeRow
        || $codeRow['status'] !== 'unclaimed'
        || ($codeRow['expires_at'] !== null && $codeRow['expires_at'] < now_dt())) {
        $pdo->rollBack();
        fail('That code is no longer available. Please check it, or log in if you already set up your toolkit.', 409);
    }

    // Universal code slot cap: refuse once the code is full. Counting under the
    // FOR UPDATE lock on this code row makes concurrent claims safe. max_uses is
    // read separately so this still works before the migration adds the column.
    if ($isUniversal && access_codes_slots_supported($pdo)) {
        $mx = $pdo->prepare('SELECT max_uses FROM access_codes WHERE id = ? LIMIT 1');
        $mx->execute([(int)$codeRow['id']]);
        $capMax = $mx->fetchColumn();
        if (!empty($capMax)) {
            $capMax  = (int)$capMax;
            $usedNow = code_use_count($pdo, (int)$codeRow['id']);
            if ($usedNow >= $capMax) {
                $pdo->rollBack();
                fail('This code has reached its limit. Please ask the host for a new code.', 409);
            }
            // Does THIS sign-up fill the last slot? (notify admins after commit)
            if ($usedNow + 1 >= $capMax) {
                $universalJustFilled = ['code' => (string)$codeRow['code_display'], 'used' => $capMax];
            }
        }
    }

    // Email must be free.
    $exists = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $exists->execute([$email]);
    if ($exists->fetch()) {
        $pdo->rollBack();
        fail('An account already exists for this email. Please log in instead.', 409);
    }

    $nowStr = now_dt();

    // 1. Create the user.
    $insUser = $pdo->prepare(
        'INSERT INTO users (email, password_hash, role, status, created_at)
         VALUES (?, ?, "learner", "active", ?)'
    );
    $insUser->execute([$email, password_hash($password, PASSWORD_DEFAULT), $nowStr]);
    $userId = (int)$pdo->lastInsertId();

    // 2. Claim the code. A single-use code flips to "claimed" (conditional, to
    //    avoid a race). A universal code stays unclaimed so others can use it.
    if (!$isUniversal) {
        $claim = $pdo->prepare(
            'UPDATE access_codes
                SET status = "claimed", claimed_by_user_id = ?, claimed_at = ?, issued_to_email = ?
              WHERE id = ? AND status = "unclaimed"'
        );
        $claim->execute([$userId, $nowStr, $email, (int)$codeRow['id']]);
        if ($claim->rowCount() !== 1) {
            $pdo->rollBack();
            fail('That code was just claimed. Please log in.', 409);
        }
    }

    // 3. Seed the carry-forward profile object.
    $insProfile = $pdo->prepare(
        'INSERT INTO user_profile (user_id, profile_json, version, updated_at)
         VALUES (?, ?, 1, ?)'
    );
    $insProfile->execute([$userId, '{}', $nowStr]);

    // 4. Create gate progress rows. Gate 1 unlocked now, 2 and 3 locked.
    $insGate = $pdo->prepare(
        'INSERT INTO gate_progress (user_id, gate_number, tools_required, tools_completed, unlocked_at)
         VALUES (?, ?, ?, 0, ?)'
    );
    foreach (GATES as $n => $g) {
        $insGate->execute([$userId, $n, $g['tools_required'], $n === 1 ? $nowStr : null]);
    }

    // 5. Claim the Welcome Buddy code (trigger 'login'). Idempotent.
    $welcome = wyzai_claim($pdo, $userId, 'login');

    // 6. Record this code use, so a shared universal code accrues one row per
    //    sign-up and the admin can reconcile the emails against purchases.
    record_redemption($pdo, (int)$codeRow['id'], $userId, $email, $codeRow['batch_label'] ?? null);

    $pdo->commit();
} catch (\Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('set-password failed: ' . $ex->getMessage());
    fail('We could not set up your toolkit just now. Please try again in a moment.', 500);
}

// 6. Queue the welcome email (logged now, sent for real at Stage E).
$coach = $welcome['coach_name'] ?? 'Welcome Buddy';
$wcode = $welcome['code'] ?? '';
$subject = 'Welcome to your DIY Creator Starter Toolkit';
$html = welcome_email_html($coach, $wcode);
$text = welcome_email_text($coach, $wcode);
mail_queue('welcome', $email, $subject, $html, $text, $userId);

// If this sign-up filled a capped universal code, tell the admins to rotate it.
if ($universalJustFilled !== null) {
    notify_admins_universal_full($pdo, $universalJustFilled['code'], (int)$universalJustFilled['used']);
}

// Log them straight in.
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;

$upd = $pdo->prepare('UPDATE users SET last_login_at = ? WHERE id = ?');
$upd->execute([now_dt(), $userId]);

json_out(['ok' => true, 'redirect' => '/dashboard.php']);


// ── Minimal welcome email bodies (final copy lands at Stage E) ───────────
function welcome_email_html(string $coach, string $code): string
{
    $codeLine = $code !== '' && strpos($code, 'PLACEHOLDER') === false
        ? '<p>Your first WyzAI coach code is <strong>' . e($code) . '</strong>. It opens ' . e($coach) . ', your guide inside the toolkit.</p>'
        : '';
    return '<div style="font-family:Inter,Arial,sans-serif;max-width:560px">'
        . '<h1 style="font-family:Montserrat,Arial,sans-serif">You are in.</h1>'
        . '<p>Your DIY Creator Starter Toolkit is ready. It moves through three gates: Get Clear, Build Your Offer, then Price, Launch, Sell.</p>'
        . '<p>Start with Gate 1. Finish the three tools there and Gate 2 opens.</p>'
        . $codeLine
        . '<p><a href="' . e(APP['app_url']) . '/dashboard.php">Open your dashboard</a></p>'
        . '</div>';
}
function welcome_email_text(string $coach, string $code): string
{
    $codeLine = $code !== '' && strpos($code, 'PLACEHOLDER') === false
        ? "Your first WyzAI coach code is $code. It opens $coach, your guide inside the toolkit.\n\n"
        : '';
    return "You are in.\n\n"
        . "Your DIY Creator Starter Toolkit is ready. It moves through three gates: Get Clear, Build Your Offer, then Price, Launch, Sell.\n\n"
        . "Start with Gate 1. Finish the three tools there and Gate 2 opens.\n\n"
        . $codeLine
        . 'Open your dashboard: ' . APP['app_url'] . "/dashboard.php\n";
}
