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
    } else {
        $body .= '<h2>Gate ' . (int)$gateNumber . ' complete</h2>';
    }

    return [$json, '<div class="gate-summary">' . $body . '</div>'];
}
