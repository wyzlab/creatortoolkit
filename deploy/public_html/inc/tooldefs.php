<?php
/**
 * tooldefs.php — the canonical definition of each tool. ONE source, read by
 * both the server (validation, scoring, results, carry-forward) and the
 * client (rendering). The tool page emits the relevant definition to the
 * browser as JSON; the engine renders from it. Nothing secret lives here.
 *
 * Reconstructed field-for-field from the published source PDFs, since the
 * Field-Map spec was lost. Product ids come from each PDF's checkout link.
 *
 * A field:
 *   key, type (text|textarea|select|radio|checkbox|checklist|note),
 *   label, hint?, required?, placeholder?, options?/items?,
 *   prefillFrom? (a profile path or list of paths, first non-empty wins)
 *
 * writesToProfile maps 'profile.path' => 'answer_field_key'. On completion the
 * answer is written to the shared profile so later tools can carry it forward.
 *
 * Gate 2 and 3 tools are added in Stages C and D.
 */

declare(strict_types=1);

function tool_defs(): array
{
    return [

    // ══ GATE 1, TOOL 1 ═══════════════════════════════════════════════════
    'ideal-client-avatar' => [
        'gate' => 1,
        'title' => 'Ideal Client Avatar Kit',
        'lede' => 'Go from "I help everyone" to one clear client you can market to with confidence. Finish it in one sitting.',
        'scoring' => ['type' => 'none'],
        'result' => 'avatar-card',
        'wyzai_prompt' =>
            "Act as my Clarity Coach. I am a Filipino coach, consultant, or creator who helps people with [your expertise]. "
            . "Ask me one question at a time to help me name ONE ideal client: their most urgent problem, their desired outcome, "
            . "their top objection, and where they hang out online. After I answer, draft a one-sentence statement: "
            . "I help [avatar] go from [problem] to [desired outcome] through [offer].",
        'writesToProfile' => [
            'avatar.name' => 'avatar_name',
            'avatar.quote' => 'avatar_quote',
            'avatar.age_range' => 'age_range',
            'avatar.location' => 'location',
            'avatar.role' => 'role_business',
            'avatar.income' => 'income_level',
            'avatar.life_stage' => 'life_stage',
            'avatar.top_goal' => 'top_goal',
            'avatar.core_value' => 'core_value',
            'avatar.urgent_problem' => 'urgent_problem',
            'avatar.pains_fears' => 'pains_fears',
            'avatar.desired_outcome' => 'desired_outcome',
            'avatar.top_objection' => 'top_objection',
            'avatar.platforms' => 'platforms',
            'avatar.positioning' => 'positioning',
        ],
        'steps' => [
            [
                'title' => 'Who is your one ideal client?',
                'help' => 'One real person, not a crowd. Give them a name.',
                'fields' => [
                    ['key' => 'avatar_name', 'type' => 'text', 'required' => true,
                     'label' => 'Avatar name', 'placeholder' => 'e.g. Maribel, the weekend baker from Cebu'],
                    ['key' => 'avatar_quote', 'type' => 'textarea', 'required' => true,
                     'label' => 'One line they would say, in their own voice',
                     'placeholder' => 'e.g. "I have great recipes but no idea how to sell them."'],
                ],
                'stuck' => 'Picture one person you have already helped, or wish you could. Start with their first name.',
            ],
            [
                'title' => 'What is their world, briefly?',
                'help' => 'Keep it light. Context only, not a biography.',
                'fields' => [
                    ['key' => 'age_range', 'type' => 'text', 'label' => 'Age range', 'placeholder' => 'e.g. 28 to 40'],
                    ['key' => 'location', 'type' => 'text', 'label' => 'Location', 'placeholder' => 'e.g. Cebu City'],
                    ['key' => 'role_business', 'type' => 'text', 'required' => true,
                     'label' => 'Role or business', 'placeholder' => 'e.g. home baker'],
                    ['key' => 'income_level', 'type' => 'text', 'label' => 'Income or budget level', 'placeholder' => 'e.g. PHP 15,000 to 30,000 monthly'],
                    ['key' => 'life_stage', 'type' => 'text', 'label' => 'Life stage', 'placeholder' => 'e.g. side hustle, evenings free'],
                ],
            ],
            [
                'title' => 'What do they want, and what do they care about?',
                'fields' => [
                    ['key' => 'top_goal', 'type' => 'textarea', 'required' => true,
                     'label' => 'Their one top goal', 'placeholder' => 'e.g. launch and fill one paid online class'],
                    ['key' => 'core_value', 'type' => 'textarea',
                     'label' => 'The one value that drives them', 'placeholder' => 'e.g. providing for family with a skill they already have'],
                ],
            ],
            [
                'title' => 'What is the ONE problem they are trying to solve right now?',
                'help' => 'Force yourself to one. This is the field that drives the sale.',
                'fields' => [
                    ['key' => 'urgent_problem', 'type' => 'textarea', 'required' => true,
                     'label' => 'Their single most urgent problem',
                     'placeholder' => 'Type the one urgent problem here'],
                ],
                'stuck' => 'If you wrote a list, circle the most urgent one and cut the rest. One problem becomes your headline.',
            ],
            [
                'title' => 'What keeps them stuck, and what does "solved" feel like?',
                'fields' => [
                    ['key' => 'pains_fears', 'type' => 'textarea',
                     'label' => 'Pains and fears, in their words', 'placeholder' => 'e.g. afraid to charge, unsure anyone will pay'],
                    ['key' => 'desired_outcome', 'type' => 'textarea', 'required' => true,
                     'label' => 'Desired outcome and how success feels', 'placeholder' => 'e.g. proud, booked out, finally paid for my skill'],
                ],
            ],
            [
                'title' => 'Why might they hesitate, and who do they trust?',
                'fields' => [
                    ['key' => 'top_objection', 'type' => 'textarea',
                     'label' => 'Their top objection to buying', 'placeholder' => 'e.g. "I do not have time" or "it might not work for me"'],
                    ['key' => 'platforms', 'type' => 'textarea',
                     'label' => 'Platforms, groups, and creators they follow', 'placeholder' => 'e.g. Filipino baking groups on Facebook, TikTok tutorials, YouTube'],
                ],
            ],
            [
                'title' => 'Can you say who you help in one sentence?',
                'help' => 'This is your Clarity gate proof. Say it out loud. If it feels sharp and true, your avatar is done.',
                'fields' => [
                    ['key' => 'positioning', 'type' => 'textarea', 'required' => true,
                     'label' => 'I help ___ go from ___ to ___ through ___',
                     'placeholder' => 'I help home bakers go from scattered recipes to a launched online class through a simple step-by-step course.'],
                ],
            ],
            [
                'title' => 'Is your avatar ready?',
                'help' => 'A quick readiness check. These shape your result, they do not block you.',
                'fields' => [
                    ['key' => 'readiness', 'type' => 'checklist', 'label' => 'Readiness check',
                     'items' => [
                        ['key' => 'has_name_voice', 'label' => 'It has a name and a real voice'],
                        ['key' => 'one_problem', 'label' => 'It names ONE urgent problem'],
                        ['key' => 'one_outcome', 'label' => 'It names ONE desired outcome'],
                        ['key' => 'one_sentence', 'label' => 'It fits in one clean sentence'],
                     ]],
                    ['key' => 'val_person_1', 'type' => 'text', 'label' => 'One real person you will test this on'],
                    ['key' => 'val_person_2', 'type' => 'text', 'label' => 'A second real person'],
                    ['key' => 'val_person_3', 'type' => 'text', 'label' => 'A third real person'],
                ],
            ],
        ],
    ],

    // ══ GATE 1, TOOL 2 ═══════════════════════════════════════════════════
    'course-clarity-framework' => [
        'gate' => 1,
        'title' => 'Course Clarity Framework',
        'lede' => 'Say what you teach, who it is for, and what you offer. In just three sentences.',
        'scoring' => ['type' => 'none'],
        'result' => 'clarity-statement',
        'wyzai_prompt' =>
            "Act as my course Clarity Coach. Ask me one question at a time to sharpen my niche, my ideal learner, and a "
            . "one-line offer. Then give me a tighter version of each.",
        'writesToProfile' => [
            'clarity.niche' => 'niche',
            'clarity.learner_name' => 'learner_name',
            'clarity.learner_role' => 'learner_role',
            'clarity.pain' => 'learner_pain',
            'clarity.want' => 'learner_want',
            'clarity.offer_from' => 'offer_from',
            'clarity.offer_to' => 'offer_to',
        ],
        'steps' => [
            [
                'title' => 'Which feels most true for you right now?',
                'help' => 'This is a clarity gap, not a skills gap. Naming it helps.',
                'fields' => [
                    ['key' => 'blocker', 'type' => 'radio', 'label' => 'Tick the one that fits',
                     'options' => [
                        'I have too many interests, I cannot pick one',
                        'Who am I to teach this?',
                        'The market is saturated, maybe no one will buy',
                        'I do not know who this is for',
                     ]],
                ],
            ],
            [
                'title' => 'Answer three questions, find one niche',
                'help' => 'Your niche lives where what you know, what you love to talk about, and what you get asked about meet.',
                'fields' => [
                    ['key' => 'niche_know', 'type' => 'textarea', 'label' => 'What do you already know or do well?'],
                    ['key' => 'niche_talk', 'type' => 'textarea', 'label' => 'What could you talk about for an hour without getting bored?'],
                    ['key' => 'niche_asked', 'type' => 'textarea', 'label' => 'What do people already come to you for?'],
                    ['key' => 'niche', 'type' => 'text', 'required' => true,
                     'label' => 'My niche is', 'placeholder' => 'name your one niche'],
                ],
                'stuck' => 'You do not need to be THE expert. You only need to be a few steps ahead of the person you want to help.',
            ],
            [
                'title' => 'Not everyone. One person.',
                'help' => 'Your answers carry over from your Avatar Kit. Edit anything that should read differently here.',
                'fields' => [
                    ['key' => 'learner_name', 'type' => 'text', 'required' => true,
                     'label' => 'Name', 'prefillFrom' => 'avatar.name'],
                    ['key' => 'learner_role', 'type' => 'text', 'required' => true,
                     'label' => 'Who they are (role or life situation)', 'prefillFrom' => 'avatar.role'],
                    ['key' => 'learner_pain', 'type' => 'textarea', 'required' => true,
                     'label' => 'Their one painful problem right now', 'prefillFrom' => 'avatar.urgent_problem'],
                    ['key' => 'learner_want', 'type' => 'textarea', 'required' => true,
                     'label' => 'What they want instead', 'prefillFrom' => 'avatar.desired_outcome'],
                ],
            ],
            [
                'title' => 'One line. An action verb.',
                'help' => 'Use a strong verb: take, reach, move, launch, get, land. Cut "understand", "learn about", "explore".',
                'fields' => [
                    ['key' => 'offer_from', 'type' => 'textarea', 'required' => true,
                     'label' => 'From (the pain)', 'prefillFrom' => ['clarity.pain', 'avatar.urgent_problem']],
                    ['key' => 'offer_to', 'type' => 'textarea', 'required' => true,
                     'label' => 'To (the outcome)', 'prefillFrom' => ['clarity.want', 'avatar.desired_outcome']],
                ],
            ],
            [
                'title' => 'A small test before you build',
                'help' => 'Show your one-line offer to three people who fit your ideal learner. Watch the reaction. That is the signal.',
                'fields' => [
                    ['key' => 'reaction_1', 'type' => 'text', 'label' => 'Person 1 reaction'],
                    ['key' => 'reaction_2', 'type' => 'text', 'label' => 'Person 2 reaction'],
                    ['key' => 'reaction_3', 'type' => 'text', 'label' => 'Person 3 reaction'],
                ],
            ],
        ],
    ],

    // ══ GATE 1, TOOL 3 ═══════════════════════════════════════════════════
    'course-idea-validation' => [
        'gate' => 1,
        'title' => 'Will It Sell? Validation Check',
        'lede' => 'Find out if people will actually pay, before you build anything.',
        'scoring' => ['type' => 'banded', 'compute' => 'validation_verdict'],
        'result' => 'validation-verdict',
        'wyzai_prompt' =>
            "Act as a course validation coach. My course idea is [describe your idea in one sentence]. My target audience is "
            . "[who it is for]. The one result a buyer gets is [outcome]. Give me: (1) five short questions I can ask my audience "
            . "to test if they actually want this, (2) a simple one-page pre-sell offer I can post, and (3) three low-cost ways to "
            . "find whether people will pay before I build anything. Keep it practical and beginner friendly.",
        'writesToProfile' => [
            'validation.idea' => 'idea_sentence',
            'validation.audience' => 'audience',
            'validation.outcome' => 'outcome',
            'validation.threshold' => 'threshold',
            'validation.commitments' => 'commitments_number',
        ],
        'steps' => [
            [
                'title' => 'What are you checking?',
                'help' => 'Your Clarity work carries over. Edit if this idea is different.',
                'fields' => [
                    ['key' => 'idea_sentence', 'type' => 'textarea', 'required' => true,
                     'label' => 'Your course idea in one sentence', 'prefillFrom' => 'avatar.positioning'],
                    ['key' => 'audience', 'type' => 'text', 'required' => true,
                     'label' => 'Who it is for', 'prefillFrom' => ['clarity.learner_role', 'avatar.role']],
                    ['key' => 'outcome', 'type' => 'textarea', 'required' => true,
                     'label' => 'The one result a buyer gets', 'prefillFrom' => ['clarity.want', 'avatar.desired_outcome']],
                ],
            ],
            [
                'title' => 'The Signal Scorecard',
                'help' => 'Tick each signal that is true. This is your starting read, not your final proof.',
                'fields' => [
                    ['key' => 'signals', 'type' => 'checklist', 'label' => 'Signals present',
                     'items' => [
                        ['key' => 'sig_painful', 'label' => 'Painful, recurring problem people keep asking about'],
                        ['key' => 'sig_search', 'label' => 'Search and content demand for the topic'],
                        ['key' => 'sig_spend', 'label' => 'People already pay to solve this'],
                        ['key' => 'sig_audience', 'label' => 'You can name exactly who it is for'],
                        ['key' => 'sig_outcome', 'label' => 'You can state the one result a buyer gets'],
                        ['key' => 'sig_access', 'label' => 'You can reach a handful of these people directly'],
                        ['key' => 'sig_willingness', 'label' => 'Someone asked "how much" or "when can I join"'],
                     ]],
                ],
            ],
            [
                'title' => 'Your 3-step test plan',
                'help' => 'Roughly two to four weeks. No finished course needed. You can save this and come back.',
                'fields' => [
                    ['key' => 'ask_who', 'type' => 'textarea', 'label' => 'Who I will ask (5 to 10 people or one group)'],
                    ['key' => 'ask_questions', 'type' => 'textarea', 'label' => 'The 3 questions I will ask'],
                    ['key' => 'offer_price', 'type' => 'text', 'label' => "Price or Founder's price", 'placeholder' => "e.g. PHP 1,500 Founder's price"],
                    ['key' => 'offer_commitment', 'type' => 'text', 'label' => 'The commitment I will ask for (waitlist, deposit, or pre-sale)'],
                ],
            ],
            [
                'title' => 'Set your threshold, then read the result',
                'help' => 'Decide your "winner" number BEFORE you test. That keeps you honest. Leave commitments blank if you have not run the test yet.',
                'fields' => [
                    ['key' => 'threshold', 'type' => 'text', 'required' => true,
                     'label' => 'The threshold I set before testing', 'placeholder' => 'e.g. 10 pre-sale slots at PHP 1,500'],
                    ['key' => 'threshold_number', 'type' => 'number', 'label' => 'That threshold as a number of commitments', 'placeholder' => 'e.g. 10'],
                    ['key' => 'commitments_number', 'type' => 'number', 'label' => 'Real commitments I actually got (leave blank until you have tested)', 'placeholder' => 'e.g. 12'],
                ],
                'stuck' => 'A "no" now is cheaper than a "no" after two months of recording. If you have not tested yet, finish anyway. Your verdict will wait for you as PENDING.',
            ],
        ],
    ],

    ];
}

/** One tool definition, or null. */
function tool_def(string $slug): ?array
{
    static $defs = null;
    if ($defs === null) { $defs = tool_defs(); }
    return $defs[$slug] ?? null;
}
