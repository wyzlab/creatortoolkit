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

        default:
            $body .= '<h2>' . e($title) . '</h2><p>Your answers are saved.</p>';
    }

    $html = '<div class="tool-result">' . $body . '</div>' . render_legal_blocks($slug);
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

        $body .= '<h2>Gate 1 complete: you got clear</h2>';
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

        $body .= '<h2>Gate 2 complete: you built your offer</h2>';
        $body .= '<p>You shaped an offer, sparked a course from your content, and audited it. Here it is in one place.</p>';
        $body .= '<div class="result-highlight">';
        if ($offerWho !== '') $body .= '<p><strong>Your offer:</strong> help ' . e($offerWho) . ' reach ' . e($offerResult)
                                     . ($offerPrice !== '' ? ' for ' . e($offerPrice) : '') . '.</p>';
        if ($theme !== '') $body .= '<p><strong>Course theme:</strong> ' . e($theme) . '</p>';
        $body .= '</div>';
        $json += ['offer_who' => $offerWho, 'theme' => $theme];
    } else {
        $body .= '<h2>Gate ' . (int)$gateNumber . ' complete</h2>';
    }

    return [$json, '<div class="gate-summary">' . $body . '</div>'];
}
