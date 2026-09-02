<?php
/**
 * POST /api/calc-fees.php  {mode, amount, method_key}
 *   -> {headline, fee, take_home, all_methods:[...]}
 *
 * mode "forward": amount is the headline. mode "backward": amount is the target
 * take-home, and we solve for the headline. Server-authoritative; the client
 * calculator mirrors this for instant feedback. Login required.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';
require_once __DIR__ . '/../inc/fees.php';

api_require_login();
require_post();
csrf_check();

$in     = json_input();
$mode   = ($in['mode'] ?? 'forward') === 'backward' ? 'backward' : 'forward';
$amount = (float)($in['amount'] ?? 0);
$key    = (string)($in['method_key'] ?? '');

if ($amount <= 0) {
    fail('Enter an amount greater than zero.');
}

$fees = load_fees(db());
if (!isset($fees[$key])) {
    fail('Choose a payment method.');
}
$method = $fees[$key];

if ($mode === 'backward') {
    $headline = fee_solve_headline($amount, $method);
} else {
    $headline = round($amount, 2);
}

$f = fee_forward($headline, $method);
json_out([
    'mode' => $mode,
    'headline' => $headline,
    'fee' => $f['fee'],
    'take_home' => $f['take_home'],
    'all_methods' => fee_all_methods($headline, $fees),
]);
