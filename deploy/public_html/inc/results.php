<?php
/**
 * results.php — builds result HTML for a completed tool and summary HTML for a
 * closed gate. Everything user-typed is escaped with e(). Result HTML is
 * assembled from known fields on a whitelist, never echoed raw, because this
 * same HTML gets emailed.
 */

declare(strict_types=1);

require_once __DIR__ . '/tooldefs.php';
require_once __DIR__ . '/toolengine.php';
require_once __DIR__ . '/result-blocks.php';

/** Small helper: a labelled result line, value escaped. */
function rline(string $label, ?string $value): string
{
    if ($value === null || trim($value) === '') return '';
    return '<div class="result-line"><span class="result-line__label">' . e($label)
         . '</span><span class="result-line__value">' . e($value) . '</span></div>';
}

/**
 * Build [result_json, result_html] for a completed tool.
 * $profile is the post-write profile, $answers the submitted answers.
 */
function build_tool_result(string $slug, array $answers, array $profile): array
{
    $def = tool_def($slug);
    $template = $def['result'] ?? 'generic';
    $title = $def['title'];
    $json = ['tool' => $slug];
    $body = '';

    switch ($template) {
        case 'avatar-card':
            $name = (string)($answers['avatar_name'] ?? '');
            $body .= '<h2>Your ideal client: ' . e($name) . '</h2>';
            $body .= rline('In their words', (string)($answers['avatar_quote'] ?? ''));
            $body .= rline('Their one urgent problem', (string)($answers['urgent_problem'] ?? ''));
            $body .= rline('What "solved" looks like', (string)($answers['desired_outcome'] ?? ''));
            $body .= '<div class="result-highlight"><h3>Your one sentence</h3><p>'
                   . e((string)($answers['positioning'] ?? '')) . '</p></div>';
            $json['name'] = $name;
            break;

        case 'clarity-statement':
            $niche   = (string)($answers['niche'] ?? '');
            $lname   = (string)($answers['learner_name'] ?? '');
            $lrole   = (string)($answers['learner_role'] ?? '');
            $pain    = (string)($answers['learner_pain'] ?? '');
            $want    = (string)($answers['learner_want'] ?? '');
            $from    = (string)($answers['offer_from'] ?? '');
            $to      = (string)($answers['offer_to'] ?? '');
            $body .= '<h2>Your Clarity Statement</h2>';
            $body .= '<div class="result-highlight">';
            $body .= '<p><strong>My niche is</strong> ' . e($niche) . '.</p>';
            $body .= '<p><strong>My ideal learner is</strong> ' . e($lname);
            if ($lrole !== '') $body .= ', a ' . e($lrole);
            $body .= ' who struggles with ' . e($pain) . ' and wants ' . e($want) . '.</p>';
            $body .= '<p><strong>I help</strong> ' . e($lname !== '' ? $lname : $lrole)
                   . ' go from ' . e($from) . ' to ' . e($to) . '.</p>';
            $body .= '</div>';
            $json += ['niche' => $niche, 'learner' => $lname, 'from' => $from, 'to' => $to];
            break;

        case 'validation-verdict':
            $v = compute_validation_verdict($answers);
            $verdict = $v['verdict'];
            $yes = $v['yes_count'];
            $copy = [
                'GO'      => 'Commitments at or above your threshold. Next: build it properly. You have real demand.',
                'REFINE'  => 'Some interest, but below your threshold. Next: tighten the audience, sharpen the outcome, or adjust the offer, then re-test. Do not build yet.',
                'RESET'   => 'Little or no commitment after a genuine test. This is a win. You just saved months. Pick a new angle or audience and run the check again.',
                'PENDING' => 'You have not run your test yet. That is fine. Your verdict waits here as PENDING. Run the 3-step test over the next few weeks, then come back and enter your real commitments.',
            ];
            $body .= '<h2>Your verdict</h2>';
            $body .= '<div class="verdict verdict--' . strtolower($verdict) . '"><span class="verdict__badge">' . e($verdict) . '</span>';
            $body .= '<p>' . e($copy[$verdict]) . '</p></div>';
            if (($answers['offer_type'] ?? '') !== '') {
                $body .= rline('What you validated', (string)$answers['offer_type']);
            }
            $body .= rline('Signal strength', $yes . ' of 7 signals present');
            $body .= rline('Your threshold', (string)($answers['threshold'] ?? ''));
            if (($answers['commitments_number'] ?? '') !== '') {
                $body .= rline('Real commitments', (string)$answers['commitments_number']);
            }
            $json += ['verdict' => $verdict, 'yes_count' => $yes];
            break;

        case 'six-part-offer':
            $who = (string)($answers['offer_who'] ?? '');
            $prob = (string)($answers['offer_problem'] ?? '');
            $res = (string)($answers['offer_result'] ?? '');
            $how = (string)($answers['offer_how'] ?? '');
            $price = (string)($answers['offer_price'] ?? '');
            $proof = (string)($answers['offer_proof'] ?? '');
            $body .= '<h2>' . e((string)($answers['offer_name'] ?? 'Your one-page offer')) . '</h2>';
            $body .= '<div class="result-highlight"><p>I help <strong>' . e($who) . '</strong> go from '
                   . e($prob) . ' to ' . e($res) . ' through ' . e($how) . ', for <strong>' . e($price) . '</strong>.</p>';
            $body .= '<p>Here is my proof: ' . e($proof) . '</p></div>';
            $checks = $answers['clarity_check'] ?? [];
            $ticked = is_array($checks) ? count(array_filter($checks)) : 0;
            $body .= '<div class="verdict verdict--' . ($ticked >= 5 ? 'go' : 'refine') . '"><span class="verdict__badge">'
                   . $ticked . ' of 5</span><p>' . ($ticked >= 5
                        ? 'You have an offer you can sell by tonight.'
                        : 'Almost. Sharpen the clarity-check items you could not tick yet.') . '</p></div>';
            $json += ['name' => $answers['offer_name'] ?? '', 'clarity' => $ticked];
            break;

        case 'spark-summary':
            $body .= '<h2>Your course spark</h2>';
            $body .= rline('Winning theme', (string)($answers['winning_theme'] ?? ''));
            $body .= rline('After this course, my student can', (string)($answers['transformation'] ?? ''));
            $mods = [];
            foreach (['module_1', 'module_2', 'module_3', 'module_4', 'module_5'] as $k) {
                if (trim((string)($answers[$k] ?? '')) !== '') { $mods[] = (string)$answers[$k]; }
            }
            if ($mods) {
                $body .= '<div class="result-highlight"><h3>Your modules</h3><ol>';
                foreach ($mods as $m) { $body .= '<li>' . e($m) . '</li>'; }
                $body .= '</ol></div>';
            }
            $body .= rline('My verdict', (string)($answers['verdict'] ?? ''));
            $json += ['theme' => $answers['winning_theme'] ?? '', 'modules' => count($mods)];
            break;

        case 'product-spark-summary':
            $body .= '<h2>Your digital product spark</h2>';
            $body .= rline('Winning theme', (string)($answers['winning_theme'] ?? ''));
            $body .= rline('After using this, my buyer can', (string)($answers['transformation'] ?? ''));
            $parts = [];
            foreach (['module_1', 'module_2', 'module_3', 'module_4', 'module_5'] as $k) {
                if (trim((string)($answers[$k] ?? '')) !== '') { $parts[] = (string)$answers[$k]; }
            }
            if ($parts) {
                $body .= '<div class="result-highlight"><h3>Your parts</h3><ol>';
                foreach ($parts as $p) { $body .= '<li>' . e($p) . '</li>'; }
                $body .= '</ol></div>';
            }
            $body .= rline('My verdict', (string)($answers['verdict'] ?? ''));
            $json += ['theme' => $answers['winning_theme'] ?? '', 'parts' => count($parts)];
            break;

        case 'checklist-scorecard':
            $sc = compute_checklist_score($answers);
            $band = $sc['band'];
            $copy = [
                'ready'    => 'Handa ka nang mag-launch. You are ready to launch.',
                'almost'   => 'Malapit na. Close. Fix the gaps and you are there.',
                'building' => 'Solid na simula. A solid start, with more to build.',
                'clarity'  => 'Clarity muna. Clarity is the first unlock. Start there.',
            ];
            $body .= '<h2>Your score</h2>';
            $body .= '<div class="verdict verdict--' . ($band === 'ready' ? 'go' : ($band === 'clarity' ? 'reset' : ($band === 'almost' ? 'refine' : 'pending')))
                   . '"><span class="verdict__badge">' . $sc['score'] . ' / 20</span><p>' . e($copy[$band]) . '</p></div>';
            $body .= '<p class="muted">This is not a grade. It is a map of what to do next. No shaming for a low score.</p>';
            $body .= rline('Top 3 gaps I will fix first', (string)($answers['top_3_gaps'] ?? ''));
            $json += ['score' => $sc['score'], 'band' => $band];
            break;

        case 'starter-decisions':
            $body .= '<h2>Your three decisions</h2>';
            $body .= rline('My skill', (string)($answers['skill'] ?? ''));
            $body .= rline('My first product', (string)($answers['product_type'] ?? ''));
            $pays = $answers['payment_method'] ?? [];
            $payMap = ['pay_ewallet' => 'E-wallets', 'pay_bank' => 'Bank transfer', 'pay_qrph' => 'QR Ph', 'pay_cards' => 'Cards and PayPal'];
            $chosen = [];
            if (is_array($pays)) { foreach ($pays as $k => $on) { if ($on && isset($payMap[$k])) { $chosen[] = $payMap[$k]; } } }
            $body .= rline('How my buyers will pay me', implode(', ', $chosen));
            $body .= '<div class="result-highlight"><h3>Your 7-day plan</h3><ol>'
                   . '<li>Pick one skill.</li><li>Pick one product type.</li><li>Set one payment method.</li>'
                   . '<li>Validate with two past clients or followers.</li><li>Tell five people what you are making.</li>'
                   . '<li>Make the first offer.</li><li>Review, tweak, then move to Course 1.</li></ol></div>';
            $json += ['skill' => $answers['skill'] ?? '', 'product' => $answers['product_type'] ?? ''];
            break;

        case 'price-card':
            require_once __DIR__ . '/fees.php';
            $price = (string)($answers['final_price'] ?? $answers['headline'] ?? '');
            $body .= '<h2>Your price</h2>';
            $body .= '<div class="result-highlight"><p style="font-size:24px;font-weight:800;color:#003D7A;">PHP ' . e($price) . '</p>';
            if (!empty($answers['priced_on'])) $body .= '<p>Priced on the outcome my client gets: ' . e((string)$answers['priced_on']) . '</p>';
            $body .= '</div>';
            $calc = $answers['fee_calc'] ?? null;
            $headline = is_array($calc) ? (float)($calc['headline'] ?? 0) : (float)($answers['headline'] ?? 0);
            if ($headline > 0) {
                $fees = load_fees(db());
                $body .= '<h3>What lands in your account</h3><div class="scroll-x"><table class="admin-table"><thead><tr><th>Method</th><th>Fee</th><th>Take-home</th></tr></thead><tbody>';
                foreach (fee_all_methods($headline, $fees) as $row) {
                    $body .= '<tr><td>' . e($row['label']) . '</td><td>PHP ' . number_format($row['fee'], 2)
                           . '</td><td>PHP ' . number_format($row['take_home'], 2) . '</td></tr>';
                }
                $body .= '</tbody></table></div>';
            }
            $json += ['price' => $price];
            break;

        case 'launch-plan':
            $verdict = (string)(profile_get($profile, 'validation.verdict') ?? 'PENDING');
            $expect = [
                'GO'      => 'Your Gate 1 validation said GO. You have real demand, so launch with confidence and keep showing up all week.',
                'REFINE'  => 'Your Gate 1 validation said REFINE. Keep this first launch small and warm while you sharpen the offer.',
                'RESET'   => 'Your Gate 1 validation said RESET. Treat this as a soft test, and be ready to adjust the angle or audience.',
                'PENDING' => 'You have not finished your validation test yet. Run it alongside this launch, and treat a quiet day one as normal.',
            ];
            $body .= '<h2>Your first launch plan</h2>';
            $body .= '<div class="notice notice--stale"><span>' . e($expect[$verdict] ?? $expect['PENDING']) . '</span></div>';
            $body .= rline('This launch is for', (string)($answers['one_person'] ?? ''));
            $body .= rline('My target first sales', (string)($answers['target_sales'] ?? ''));
            $countPhase = function ($set) { $n = 0; if (is_array($set)) foreach ($set as $v) if ($v) $n++; return $n; };
            $body .= '<div class="result-highlight"><p><strong>Before:</strong> ' . $countPhase($answers['before'] ?? []) . ' of 7 done</p>'
                   . '<p><strong>Launch week:</strong> ' . $countPhase($answers['during'] ?? []) . ' of 5 done</p>'
                   . '<p><strong>After:</strong> ' . $countPhase($answers['after'] ?? []) . ' of 5 done</p></div>';
            $body .= '<p class="muted">A quiet day one is not a failed launch. Warmth beats size. Keep going.</p>';
            $json += ['verdict' => $verdict];
            break;

        case 'call-script':
            // Built to be read one-handed at 375px during a live call.
            $stage = function ($n, $title, $say) {
                if (trim((string)$say) === '') return '';
                return '<div class="result-highlight"><h3>' . e($n . '. ' . $title) . '</h3><p>' . nl2br(e((string)$say)) . '</p></div>';
            };
            $body .= '<h2>Your call script</h2>';
            $body .= '<p class="muted">Keep this open during the call. Six stages, about 20 to 30 minutes. Diagnose before you prescribe.</p>';
            $body .= $stage('0', 'Before the call (intake)', $answers['intake_questions'] ?? '');
            $body .= $stage('1', 'Connect', $answers['opener'] ?? '');
            $body .= $stage('2', 'Frame', $answers['framing'] ?? '');
            $diag = trim((string)($answers['diagnosis_q1'] ?? '') . "\n" . (string)($answers['diagnosis_q2'] ?? ''));
            $body .= $stage('3', 'Diagnose (listen 70%)', $diag);
            $body .= $stage('4', 'Prescribe (only if it fits)', $answers['prescribe'] ?? '');
            $inv = trim((string)($answers['price_sentence'] ?? '') . (empty($answers['feared_objection']) ? '' : "\n\nIf they hesitate: " . $answers['feared_objection']));
            $body .= $stage('5', 'Invite (say the price, then breathe)', $inv);
            $body .= $stage('6', 'Follow-up', $answers['followup'] ?? '');
            $json += ['ready' => true];
            break;

        default:
            $body .= '<h2>' . e($title) . '</h2><p>Your answers are saved.</p>';
    }

    // The Terms of Use and Proof of Authorship blocks are intentionally NOT shown
    // on the on-screen result: the downloadable PDF already carries both. (The
    // render_legal_blocks() helper in result-blocks.php is kept for reuse.)
    $html = '<div class="tool-result">' . $body . '</div>';
    return [$json, $html];
}

/**
 * Build [summary_json, summary_html] for a closed gate, from the profile and
 * the gate's tool results. Gate 1 is the Clarity summary.
 */
function build_gate_summary(int $gateNumber, array $profile): array
{
    $json = ['gate' => $gateNumber];
    $body = '';

    if ($gateNumber === 1) {
        $niche = (string)(profile_get($profile, 'clarity.niche') ?? '');
        $lname = (string)(profile_get($profile, 'clarity.learner_name') ?? profile_get($profile, 'avatar.name') ?? '');
        $from  = (string)(profile_get($profile, 'clarity.offer_from') ?? profile_get($profile, 'avatar.urgent_problem') ?? '');
        $to    = (string)(profile_get($profile, 'clarity.offer_to') ?? profile_get($profile, 'avatar.desired_outcome') ?? '');
        $verdict = (string)(profile_get($profile, 'validation.verdict') ?? 'PENDING');

        $body .= '<h2>Gate 1: You Got It Clear</h2>';
        $body .= '<p>You named who you help, what you teach, and what you offer. Here it is in one place.</p>';
        $body .= '<div class="result-highlight">';
        if ($niche !== '') $body .= '<p><strong>Niche:</strong> ' . e($niche) . '</p>';
        $body .= '<p><strong>You help</strong> ' . e($lname) . ' go from ' . e($from) . ' to ' . e($to) . '.</p>';
        $body .= '<p><strong>Validation verdict:</strong> ' . e($verdict) . '</p>';
        $body .= '</div>';
        $json += ['niche' => $niche, 'learner' => $lname, 'verdict' => $verdict];
    } elseif ($gateNumber === 2) {
        $offerWho = (string)(profile_get($profile, 'offer.who') ?? '');
        $offerResult = (string)(profile_get($profile, 'offer.result') ?? '');
        $offerPrice = (string)(profile_get($profile, 'offer.price') ?? '');
        $theme = (string)(profile_get($profile, 'sparker.theme') ?? '');

        $body .= '<h2>Gate 2: You Built Your Offer</h2>';
        $body .= '<p>You shaped an offer, sparked a course from your content, and audited it. Here it is in one place.</p>';
        $body .= '<div class="result-highlight">';
        if ($offerWho !== '') $body .= '<p><strong>Your offer:</strong> help ' . e($offerWho) . ' reach ' . e($offerResult)
                                     . ($offerPrice !== '' ? ' for ' . e($offerPrice) : '') . '.</p>';
        if ($theme !== '') $body .= '<p><strong>Course theme:</strong> ' . e($theme) . '</p>';
        $body .= '</div>';
        $json += ['offer_who' => $offerWho, 'theme' => $theme];
    } elseif ($gateNumber === 3) {
        $price = (string)(profile_get($profile, 'pricing.final_price') ?? profile_get($profile, 'pricing.headline') ?? '');
        $body .= '<h2>Gate 3: You Can Price, Launch, and Sell</h2>';
        $body .= '<p>You set a price you can defend, mapped your first launch, and have a call script ready. You have finished the toolkit.</p>';
        $body .= '<div class="result-highlight">';
        if ($price !== '') $body .= '<p><strong>Your price:</strong> PHP ' . e($price) . '</p>';
        $body .= '<p>Your discovery call script is ready to use on your next call.</p>';
        $body .= '</div>';
        $json += ['price' => $price];
    } elseif ($gateNumber === 0) {
        // The full package: everything the learner built, in one place.
        $body .= '<h2>Your complete creator toolkit</h2>';
        $body .= '<p>You went from "I help everyone" to a clear client, a course, a price, and a plan to sell it. Here is your whole journey.</p>';
        $lname = (string)(profile_get($profile, 'clarity.learner_name') ?? profile_get($profile, 'avatar.name') ?? '');
        $niche = (string)(profile_get($profile, 'clarity.niche') ?? '');
        $theme = (string)(profile_get($profile, 'sparker.theme') ?? '');
        $price = (string)(profile_get($profile, 'pricing.final_price') ?? profile_get($profile, 'pricing.headline') ?? '');
        $body .= '<div class="result-highlight">';
        if ($lname !== '') $body .= '<p><strong>Ideal client:</strong> ' . e($lname) . '</p>';
        if ($niche !== '') $body .= '<p><strong>Niche:</strong> ' . e($niche) . '</p>';
        if ($theme !== '') $body .= '<p><strong>Course theme:</strong> ' . e($theme) . '</p>';
        if ($price !== '') $body .= '<p><strong>Price:</strong> PHP ' . e($price) . '</p>';
        $body .= '</div>';
        $json += ['learner' => $lname, 'price' => $price];
    } else {
        $body .= '<h2>Gate ' . (int)$gateNumber . ' complete</h2>';
    }

    return [$json, '<div class="gate-summary">' . $body . '</div>'];
}
