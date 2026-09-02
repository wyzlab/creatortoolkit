<?php
/**
 * fees.php — the checkout fee maths, server side. The same formula runs in
 * js/fee-calc.js for instant feedback, but both read the one fee table
 * (payment_fees), so the rates are the single source of truth.
 *
 * fee = max(amount * rate%, min_fee) + fixed_fee
 * take_home = amount - fee
 *
 * Worked example (Technical Spec / PDF): PHP 500 via GCash (3% + 11) =>
 * 15 + 11 = 26 fee, 474 take-home.
 */

declare(strict_types=1);

/** Load active fee methods, keyed by method_key. */
function load_fees(PDO $pdo): array
{
    $rows = $pdo->query('SELECT method_key, label, rate_percent, min_fee, fixed_fee, sort_order
                           FROM payment_fees WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[$r['method_key']] = [
            'method_key' => $r['method_key'],
            'label' => $r['label'],
            'rate_percent' => (float)$r['rate_percent'],
            'min_fee' => $r['min_fee'] === null ? null : (float)$r['min_fee'],
            'fixed_fee' => (float)$r['fixed_fee'],
            'sort_order' => (int)$r['sort_order'],
        ];
    }
    return $out;
}

/** Forward: headline amount -> {fee, take_home}. */
function fee_forward(float $amount, array $method): array
{
    $variable = $amount * $method['rate_percent'] / 100.0;
    if ($method['min_fee'] !== null) {
        $variable = max($variable, $method['min_fee']);
    }
    $fee = round($variable + $method['fixed_fee'], 2);
    return ['fee' => $fee, 'take_home' => round($amount - $fee, 2)];
}

/**
 * Backward: target take-home -> smallest whole-peso headline that clears at
 * least the target. Handles the min-fee methods (QR Ph, bank transfer).
 */
function fee_solve_headline(float $target, array $method): float
{
    $r = $method['rate_percent'] / 100.0;
    $f = $method['fixed_fee'];
    $m = $method['min_fee'];

    // Case A: the percentage component is above any minimum.
    $ha = ($r < 1.0) ? ($target + $f) / (1.0 - $r) : $target + $f;
    if ($m === null || $ha * $r >= $m) {
        $headline = $ha;
    } else {
        // Case B: the minimum fee binds, so the fee is constant (m + f).
        $headline = $target + $m + $f;
    }
    // Round up to a clean peso so take-home is at least the target.
    $headline = ceil($headline);
    // Nudge up if rounding left us a peso short.
    while (fee_forward($headline, $method)['take_home'] < $target) {
        $headline += 1;
    }
    return $headline;
}

/** Fee and take-home for a headline across every method. */
function fee_all_methods(float $amount, array $fees): array
{
    $out = [];
    foreach ($fees as $m) {
        $r = fee_forward($amount, $m);
        $out[] = ['method_key' => $m['method_key'], 'label' => $m['label'],
                  'fee' => $r['fee'], 'take_home' => $r['take_home']];
    }
    return $out;
}
