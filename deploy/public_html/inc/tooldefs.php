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
        'lede' => 'Find out if people will actually pay, before you build anything. Works for any offer: a course, digital product, membership, webinar, event, service, or physical product.',
        'scoring' => ['type' => 'banded', 'compute' => 'validation_verdict'],
        'result' => 'validation-verdict',
        'wyzai_prompt' =>
            "Act as an offer validation coach. My idea is [describe your offer in one sentence: a course, digital product, "
            . "membership, webinar, event, service, or physical product]. My target audience is [who it is for]. The one result "
            . "a buyer gets is [outcome]. Give me: (1) five short questions I can ask my audience to test if they actually want "
            . "this, (2) a simple one-page pre-sell offer I can post, and (3) three low-cost ways to find whether people will pay "
            . "before I build anything. Keep it practical and beginner friendly.",
        'writesToProfile' => [
            'validation.offer_type' => 'offer_type',
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
                    ['key' => 'offer_type', 'type' => 'select', 'required' => true,
                     'label' => 'What are you validating?', 'placeholder' => 'Choose the type of offer',
                     'options' => [
                        'Online course', 'Digital product (ebook, template, preset)', 'Membership or community',
                        'Webinar or workshop', 'Event or ticket', 'Service or coaching package',
                        'Physical product', 'Other',
                     ]],
                    ['key' => 'idea_sentence', 'type' => 'textarea', 'required' => true,
                     'label' => 'Your idea in one sentence', 'prefillFrom' => 'avatar.positioning'],
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
                'stuck' => 'A "no" now is cheaper than a "no" after two months of building it. If you have not tested yet, finish anyway. Your verdict will wait for you as PENDING.',
            ],
        ],
    ],

    // ══ GATE 2, TOOL 1 ═══════════════════════════════════════════════════
    'one-page-offer' => [
        'gate' => 2,
        'title' => 'One-Page Offer Template',
        'lede' => 'Turn what you know into one clear offer you can actually sell.',
        'scoring' => ['type' => 'checklist', 'mode' => 'all-required',
            'items' => ['check_one_page', 'check_specific_who', 'check_result_destination', 'check_price_one_number', 'check_say_one_breath'],
            'pass' => 'You have an offer you can sell by tonight.',
            'fail' => 'Almost. Tick the ones that are true, and sharpen the rest.'],
        'result' => 'six-part-offer',
        'wyzai_prompt' =>
            "Act as a plain-spoken offer coach for a Filipino coach or creator. Here is a rough draft of my offer: "
            . "WHO: [paste], PROBLEM: [paste], RESULT: [paste], HOW: [paste], PRICE: [paste], PROOF: [paste]. "
            . "Rewrite it as one clear sentence: I help [who] go from [problem] to [result] through [how], for [price]. "
            . "Make the result a real outcome, not a list of sessions. Then give me one tip to make my price feel justified by the result.",
        'writesToProfile' => [
            'offer.name' => 'offer_name', 'offer.who' => 'offer_who', 'offer.problem' => 'offer_problem',
            'offer.result' => 'offer_result', 'offer.how' => 'offer_how', 'offer.price' => 'offer_price', 'offer.proof' => 'offer_proof',
        ],
        'steps' => [
            [
                'title' => 'Who it is for',
                'help' => 'Your Gate 1 work carries over. Edit anything that should read differently for this offer.',
                'fields' => [
                    ['key' => 'offer_name', 'type' => 'text', 'required' => true, 'label' => 'Offer name', 'placeholder' => 'e.g. First Paying Client in 6 Weeks'],
                    ['key' => 'offer_who', 'type' => 'textarea', 'required' => true, 'label' => 'Who is it for', 'hint' => 'Name one specific person and the moment they are in.', 'prefillFrom' => ['clarity.learner_role', 'avatar.role']],
                    ['key' => 'offer_problem', 'type' => 'textarea', 'required' => true, 'label' => 'The problem', 'hint' => 'The pain in their words. What are they stuck on right now?', 'prefillFrom' => ['clarity.pain', 'avatar.urgent_problem']],
                ],
                'stuck' => 'Picture one real person. Their job, their situation, the moment they are in.',
            ],
            [
                'title' => 'The result and how it works',
                'fields' => [
                    ['key' => 'offer_result', 'type' => 'textarea', 'required' => true, 'label' => 'The result', 'hint' => 'The one clear outcome. How will you both know it happened?', 'prefillFrom' => ['clarity.want', 'avatar.desired_outcome']],
                    ['key' => 'offer_how', 'type' => 'textarea', 'required' => true, 'label' => 'How it works', 'hint' => 'Format and simple structure. Keep it short.'],
                ],
            ],
            [
                'title' => 'Price and proof',
                'fields' => [
                    ['key' => 'offer_price', 'type' => 'text', 'required' => true, 'label' => 'The price', 'hint' => 'One number, tied to the transformation.', 'placeholder' => 'e.g. PHP 18,000'],
                    ['key' => 'offer_proof', 'type' => 'textarea', 'required' => true, 'label' => 'The proof', 'hint' => 'One or two honest reasons to believe you. Light, not a resume.'],
                ],
            ],
            [
                'title' => 'The clarity check',
                'help' => 'Before you call it done, tick each one that is a clear yes.',
                'fields' => [
                    ['key' => 'clarity_check', 'type' => 'checklist', 'label' => 'Run these five',
                     'items' => [
                        ['key' => 'check_one_page', 'label' => 'It fits on one page'],
                        ['key' => 'check_specific_who', 'label' => 'The "who" is one specific person, not everyone'],
                        ['key' => 'check_result_destination', 'label' => 'The result is a destination, not a list of sessions'],
                        ['key' => 'check_price_one_number', 'label' => 'The price is one number, stated plainly'],
                        ['key' => 'check_say_one_breath', 'label' => 'You can say the whole offer out loud in one breath'],
                     ]],
                ],
            ],
        ],
    ],

    // ══ GATE 2, TOOL 2 ═══════════════════════════════════════════════════
    'content-to-course-sparker' => [
        'gate' => 2,
        'title' => 'Content-to-Course Idea Sparker',
        'lede' => 'A course is just your best content, organized so someone can follow it.',
        'scoring' => ['type' => 'none'],
        'result' => 'spark-summary',
        'wyzai_prompt' =>
            "I am a Filipino content creator or educator turning my content into a course. My winning theme is [paste your theme]. "
            . "My transformation is: after this course my student can [paste transformation]. Here are the modules and outcomes I drafted: "
            . "[paste your module rows]. Please tighten this into a clear 3 to 5 module course outline. For each module give a sharp title, "
            . "a one-line outcome, and one suggested lesson. Point out any module that does not move the student toward the transformation.",
        'writesToProfile' => [
            'sparker.theme' => 'winning_theme', 'sparker.transformation' => 'transformation',
            'sparker.module_1' => 'module_1', 'sparker.module_2' => 'module_2', 'sparker.module_3' => 'module_3',
            'sparker.module_4' => 'module_4', 'sparker.module_5' => 'module_5', 'sparker.verdict' => 'verdict',
        ],
        'steps' => [
            [
                'title' => 'Mine your top content',
                'help' => 'Start with your best or most-loved content. The questions people ask you again and again are gold.',
                'fields' => [
                    ['key' => 'content_shortlist', 'type' => 'textarea', 'label' => 'Your content shortlist', 'hint' => 'List 8 to 10 of your best or most-loved pieces.', 'rows' => 4],
                    ['key' => 'questions_asked', 'type' => 'textarea', 'label' => 'Questions people ask you again and again'],
                ],
            ],
            [
                'title' => 'Cluster into themes, pick one winner',
                'help' => 'Group your content into 2 to 4 themes, then pick the one your audience asks about most.',
                'fields' => [
                    ['key' => 'theme_1', 'type' => 'text', 'label' => 'Theme 1'],
                    ['key' => 'theme_2', 'type' => 'text', 'label' => 'Theme 2'],
                    ['key' => 'theme_3', 'type' => 'text', 'label' => 'Theme 3 (optional)'],
                    ['key' => 'winning_theme', 'type' => 'text', 'required' => true, 'label' => 'My winning theme is', 'hint' => 'This is your course topic. One theme.'],
                ],
            ],
            [
                'title' => 'Name your transformation',
                'help' => 'A course is one transformation, broken into steps. Be specific.',
                'fields' => [
                    ['key' => 'transformation', 'type' => 'textarea', 'required' => true, 'label' => 'After this course, my student can', 'placeholder' => 'e.g. plan and post one week of Reels in one sitting'],
                ],
            ],
            [
                'title' => 'Your course outline (3 to 5 modules)',
                'help' => 'Break your winning theme into modules. Three strong modules beat six vague ones. This flows into your Checklist next.',
                'fields' => [
                    ['key' => 'module_1', 'type' => 'text', 'required' => true, 'label' => 'Module 1'],
                    ['key' => 'module_2', 'type' => 'text', 'required' => true, 'label' => 'Module 2'],
                    ['key' => 'module_3', 'type' => 'text', 'required' => true, 'label' => 'Module 3'],
                    ['key' => 'module_4', 'type' => 'text', 'label' => 'Module 4 (optional)'],
                    ['key' => 'module_5', 'type' => 'text', 'label' => 'Module 5 (optional)'],
                ],
            ],
            [
                'title' => 'Quick validate',
                'help' => 'You do not need certainty. You need signal. Tick what is true.',
                'fields' => [
                    ['key' => 'validate', 'type' => 'checklist', 'label' => 'Tick what is true',
                     'items' => [
                        ['key' => 'v_asks', 'label' => 'My audience already asks about this theme'],
                        ['key' => 'v_hours', 'label' => 'I can talk about it for hours'],
                        ['key' => 'v_result', 'label' => 'I can name a clear result for my student'],
                        ['key' => 'v_content', 'label' => 'Most modules already have existing content to pull from'],
                     ]],
                    ['key' => 'verdict', 'type' => 'text', 'label' => 'My verdict', 'placeholder' => 'Go, or not yet and why'],
                ],
            ],
        ],
    ],

    // ══ GATE 2, TOOL 3 ═══════════════════════════════════════════════════
    'course-design-checklist' => [
        'gate' => 2,
        'title' => '20-Point Course Design Checklist',
        'lede' => 'A self-audit so your course actually sells and actually gets finished.',
        'scoring' => ['type' => 'banded', 'compute' => 'checklist_score'],
        'result' => 'checklist-scorecard',
        'wyzai_prompt' =>
            "Act as my course Clarity Coach. Ask me five questions, one at a time, to define who my course is for and the single result "
            . "it delivers. Then write my answer as one clear sentence.",
        'writesToProfile' => [
            'checklist.title' => 'course_title', 'checklist.transformation' => 'transformation',
            'checklist.module_skeleton' => 'module_skeleton',
        ],
        'steps' => [
            [
                'title' => 'Your course',
                'fields' => [
                    ['key' => 'course_title', 'type' => 'text', 'required' => true, 'label' => 'Course title', 'prefillFrom' => 'sparker.theme'],
                ],
                'help' => 'Answer honestly, for the course you have now, not the dream version. Tick every box you can truly say yes to.',
            ],
            [
                'title' => 'Zone A. Clarity of Outcome',
                'help' => 'Buyers pay for a change, not topics. (Points 1 to 3)',
                'fields' => [
                    ['key' => 'zoneA', 'type' => 'checklist', 'label' => 'Tick what is true',
                     'items' => [
                        ['key' => 'cp1', 'label' => 'One clear transformation a stranger gets in one read'],
                        ['key' => 'cp2', 'label' => 'A before-to-after, not a topic list'],
                        ['key' => 'cp3', 'label' => 'A defined target learner, level and pain named'],
                     ]],
                    ['key' => 'transformation', 'type' => 'textarea', 'label' => 'By the end of my course, my learner will be able to', 'prefillFrom' => ['sparker.transformation', 'clarity.want']],
                ],
            ],
            [
                'title' => 'Zone B. Validation Before Building',
                'help' => 'Prove people want it before you build it. (Points 4 to 5)',
                'fields' => [
                    ['key' => 'zoneB', 'type' => 'checklist', 'label' => 'Tick what is true',
                     'items' => [
                        ['key' => 'cp4', 'label' => 'Demand checked or pre-sold (real yeses, waitlist, or pre-orders)'],
                        ['key' => 'cp5', 'label' => 'Researched the gap (three specific complaints your course answers)'],
                     ]],
                    ['key' => 'complaints', 'type' => 'textarea', 'label' => 'Three complaints about existing options my course answers'],
                ],
            ],
            [
                'title' => 'Zone C. Structure and Architecture',
                'help' => 'The path, logical and not overwhelming. Your Idea Sparker modules carry over here. (Points 6 to 8)',
                'fields' => [
                    ['key' => 'zoneC', 'type' => 'checklist', 'label' => 'Tick what is true',
                     'items' => [
                        ['key' => 'cp6', 'label' => '3 to 7 modules, not 20'],
                        ['key' => 'cp7', 'label' => 'Logical build order'],
                        ['key' => 'cp8', 'label' => 'Each module maps to one sub-outcome'],
                     ]],
                    ['key' => 'module_skeleton', 'type' => 'textarea', 'label' => 'My module skeleton (Module 1 / 2 / 3, up to 7)', 'prefillFrom' => 'sparker.modules', 'rows' => 4],
                ],
            ],
            [
                'title' => 'Zone D. Lesson Design',
                'help' => 'Lessons that teach, not info dump. (Points 9 to 11)',
                'fields' => [
                    ['key' => 'zoneD', 'type' => 'checklist', 'label' => 'Tick what is true',
                     'items' => [
                        ['key' => 'cp9', 'label' => 'Short lessons, mostly 5 to 10 minutes'],
                        ['key' => 'cp10', 'label' => 'Frameworks and practice, a "now you try" in every lesson'],
                        ['key' => 'cp11', 'label' => 'A consistent lesson template'],
                     ]],
                    ['key' => 'avg_lesson', 'type' => 'radio', 'label' => 'Average lesson length',
                     'options' => ['Under 10 minutes', '10 to 20 minutes', 'Longer, will tighten']],
                ],
            ],
            [
                'title' => 'Zone E. Engagement and Gamification',
                'help' => 'Learners who act, not just watch. (Points 12 to 14)',
                'fields' => [
                    ['key' => 'zoneE', 'type' => 'checklist', 'label' => 'Tick what is true',
                     'items' => [
                        ['key' => 'cp12', 'label' => 'An active task per module'],
                        ['key' => 'cp13', 'label' => 'A gamification layer (XP, badges, levels, streaks)'],
                        ['key' => 'cp14', 'label' => 'A community touchpoint'],
                     ]],
                    ['key' => 'game_layer', 'type' => 'text', 'label' => 'My game layer (XP / badges / levels / streaks / quests)'],
                ],
            ],
            [
                'title' => 'Zone F. Assessment and Feedback',
                'help' => 'Learners who know they got it. (Points 15 to 16)',
                'fields' => [
                    ['key' => 'zoneF', 'type' => 'checklist', 'label' => 'Tick what is true',
                     'items' => [
                        ['key' => 'cp15', 'label' => 'A knowledge check or project per outcome'],
                        ['key' => 'cp16', 'label' => 'Clear success criteria ("you nailed it when ___")'],
                     ]],
                    ['key' => 'success_criteria', 'type' => 'textarea', 'label' => 'My learner knows they got it when'],
                ],
            ],
            [
                'title' => 'Zone G. Completion and Retention',
                'help' => 'The part that gets your course finished. (Points 17 to 19)',
                'fields' => [
                    ['key' => 'zoneG', 'type' => 'checklist', 'label' => 'Tick what is true',
                     'items' => [
                        ['key' => 'cp17', 'label' => 'A welcome that sets expectations'],
                        ['key' => 'cp18', 'label' => 'Paced release plus progress tracking'],
                        ['key' => 'cp19', 'label' => 'An instructor check-in cadence'],
                     ]],
                    ['key' => 'checkin_cadence', 'type' => 'radio', 'label' => 'My check-in cadence',
                     'options' => ['Weekly', 'Every 2 weeks', 'None yet, will set one']],
                ],
            ],
            [
                'title' => 'Zone H. Trust, Pricing, and Sales',
                'help' => 'Point 20, the big one. It scores only when all four below are ticked.',
                'fields' => [
                    ['key' => 'zoneH', 'type' => 'checklist', 'label' => 'All four must be true to earn point 20',
                     'items' => [
                        ['key' => 'p20a', 'label' => '20a. Sulit, confident pricing you can defend'],
                        ['key' => 'p20b', 'label' => '20b. Honest proof, no budol, zero guaranteed results'],
                        ['key' => 'p20c', 'label' => '20c. Outline reusable as sales copy'],
                        ['key' => 'p20d', 'label' => '20d. Local checkout enabled (GCash, Maya, QR Ph, cards)'],
                     ]],
                    ['key' => 'sulit_price', 'type' => 'text', 'label' => 'My sulit price (PHP)', 'placeholder' => 'e.g. PHP 1,500'],
                    ['key' => 'sulit_why', 'type' => 'textarea', 'label' => 'Why it is sulit'],
                ],
            ],
            [
                'title' => 'Your scorecard',
                'help' => 'This is not a grade. It is a map of what to do next. No shaming for a low score.',
                'fields' => [
                    ['key' => 'top_3_gaps', 'type' => 'textarea', 'label' => 'Top 3 gaps I will fix first'],
                ],
            ],
        ],
    ],

    // ══ GATE 2, TOOL 4 ═══════════════════════════════════════════════════
    'filipino-creators-starter-kit' => [
        'gate' => 2,
        'title' => "Filipino Creator's Starter Kit",
        'lede' => 'You already have something worth selling. Let us find it, and get you paid.',
        'scoring' => ['type' => 'none'],
        'result' => 'starter-decisions',
        'wyzai_prompt' =>
            "Act as my Filipino-context creator coach. I know [your skill]. Suggest three simple first products I could sell, "
            . "who each is for, and a price in pesos. Keep it beginner-friendly.",
        'writesToProfile' => [
            'starter.skill' => 'skill', 'starter.product_type' => 'product_type', 'starter.payment_method' => 'payment_method',
        ],
        'steps' => [
            [
                'title' => 'Decision 1: your skill',
                'help' => 'Who am I to charge for this? Wrong question. Ask who your skill already helps.',
                'fields' => [
                    ['key' => 'skill', 'type' => 'text', 'required' => true, 'label' => 'One thing people already ask me for help with', 'prefillFrom' => ['clarity.niche', 'avatar.role']],
                ],
            ],
            [
                'title' => 'Decision 2: your first product',
                'help' => 'You do not need a big idea. You need one small, useful thing.',
                'fields' => [
                    ['key' => 'product_type', 'type' => 'radio', 'required' => true, 'label' => 'The one you will start with',
                     'options' => [
                        'Step-by-step guide or ebook', 'Design template or checklist', 'Mini-course or workshop',
                        'Coaching slot or consult', 'Paid community or group', 'Physical product or merch',
                     ]],
                    ['key' => 'first_product', 'type' => 'text', 'label' => 'My first product will be a ___ that helps ___ do ___'],
                    ['key' => 'price_band_note', 'type' => 'note', 'label' => 'PH beginner price band: PHP 490 to PHP 2,990. Start low enough that the first sale is reachable, high enough to signal value. This is guidance, not a required field.'],
                ],
            ],
            [
                'title' => 'Decision 3: how you get paid',
                'help' => 'Paano ako mababayaran? Plain and local.',
                'fields' => [
                    ['key' => 'payment_method', 'type' => 'checklist', 'required' => true, 'label' => 'How your buyers will pay you (tick at least one)',
                     'items' => [
                        ['key' => 'pay_ewallet', 'label' => 'E-wallets (GCash, Maya, GrabPay, ShopeePay)'],
                        ['key' => 'pay_bank', 'label' => 'Bank transfer (UnionBank, RCBC, BPI, Chinabank)'],
                        ['key' => 'pay_qrph', 'label' => 'QR Ph'],
                        ['key' => 'pay_cards', 'label' => 'Cards and PayPal (Stripe, PayPal)'],
                     ]],
                ],
            ],
            [
                'title' => 'Your 7-day plan',
                'help' => 'One small decision a day, not a second job. Adjust any day to fit you.',
                'fields' => [
                    ['key' => 'ofw_idea', 'type' => 'text', 'label' => 'If abroad: my phone-only product idea that earns in PHP (optional)'],
                    ['key' => 'gutcheck', 'type' => 'text', 'label' => 'An offer I want to gut-check for scams (optional)'],
                ],
            ],
        ],
    ],

    // ══ GATE 3, TOOL 1 ═══════════════════════════════════════════════════
    'pricing-confidence' => [
        'gate' => 3,
        'title' => 'Pricing Confidence Starter',
        'lede' => 'Set one price you can defend, checked against real checkout fees.',
        'scoring' => ['type' => 'none'],
        'result' => 'price-card',
        'wyzai_prompt' =>
            "I am a Filipino coach, consultant, or creator selling [offer] to [audience]. The outcome my client gets is [outcome]. "
            . "I am considering a headline price of PHP [amount], sold through [GCash / Maya / card / bank transfer]. Act as a pricing coach. "
            . "1) Tell me if my one-line value reason is strong or weak and why. 2) Estimate my take-home after typical PH checkout fees. "
            . "3) Suggest a fair low, mid, and high price for this offer, with a one-line reason for each. Keep it practical and Filipino-first.",
        'writesToProfile' => [
            'pricing.value_reason' => 'value_reason', 'pricing.band' => 'band',
            'pricing.headline' => 'headline', 'pricing.method' => 'method', 'pricing.final_price' => 'final_price',
        ],
        'steps' => [
            [
                'title' => 'Why you might undercharge',
                'help' => 'Tick the ones that sound like you. No judgment. The gap is almost never your skill, it is the basis you priced from.',
                'fields' => [
                    ['key' => 'undercharge', 'type' => 'checklist', 'label' => 'Tick what sounds like you',
                     'items' => [
                        ['key' => 'u_once', 'label' => 'I set my price once from what felt safe, and never moved it'],
                        ['key' => 'u_hourly', 'label' => 'I quote by the hour, so my income is capped by my time'],
                        ['key' => 'u_cheapest', 'label' => 'I compare myself to the cheapest option, not my results'],
                        ['key' => 'u_embarrassed', 'label' => 'I feel embarrassed the second I have to say the number'],
                        ['key' => 'u_nofees', 'label' => 'I set a price without thinking about checkout fees'],
                     ]],
                ],
            ],
            [
                'title' => 'Frame your value',
                'help' => 'Price the result your client walks away with, not the hours.',
                'fields' => [
                    ['key' => 'outcome', 'type' => 'textarea', 'label' => 'What does your client walk away with?', 'hint' => 'The outcome, not the hours.', 'prefillFrom' => ['clarity.want', 'avatar.desired_outcome']],
                    ['key' => 'worth', 'type' => 'textarea', 'label' => 'What is that outcome worth to them?', 'hint' => 'Time saved, income gained, stress removed.'],
                    ['key' => 'value_reason', 'type' => 'text', 'required' => true, 'label' => 'Say it in one line: "My client gets ___"'],
                ],
            ],
            [
                'title' => 'Pick your band',
                'help' => 'Do not hunt for one correct price. Place yourself on a spectrum.',
                'fields' => [
                    ['key' => 'band', 'type' => 'radio', 'required' => true, 'label' => 'My band',
                     'options' => ['Low-ticket digital (roughly PHP 195 to 1,500)', 'Coaching or service (tiered by access)', 'Consulting or training (per engagement or per head)']],
                    ['key' => 'placement', 'type' => 'radio', 'label' => 'Within that band I place myself',
                     'options' => ['Low', 'Mid', 'High']],
                    ['key' => 'band_reason', 'type' => 'text', 'label' => 'My reason (outcome plus proof)'],
                ],
            ],
            [
                'title' => 'Fee-check your price',
                'help' => 'Your headline price is not your take-home. Try amounts and methods, or work backward from what you want to clear.',
                'fields' => [
                    ['key' => 'fee_calc', 'type' => 'fee-calculator', 'label' => 'Checkout fee calculator'],
                ],
                'stuck' => 'Worked example: PHP 500 via GCash. Fee is 3% of 500 (15) plus 11, which is 26. Take-home is about 474 before tax.',
            ],
            [
                'title' => 'Set your checkout price',
                'help' => 'If you need to clear a certain amount after fees, set your headline a little above it.',
                'fields' => [
                    ['key' => 'headline', 'type' => 'text', 'required' => true, 'label' => 'My headline price (PHP)', 'placeholder' => 'e.g. 500'],
                    ['key' => 'method', 'type' => 'radio', 'required' => true, 'label' => 'My main payment method',
                     'options' => ['GCash', 'Maya', 'GrabPay', 'ShopeePay', 'QR Ph', 'Bank transfer', 'Domestic card']],
                ],
            ],
            [
                'title' => 'Say it out loud',
                'help' => 'Read it out loud once. That is the number you can defend.',
                'fields' => [
                    ['key' => 'final_price', 'type' => 'text', 'required' => true, 'label' => 'My price is (PHP)', 'prefillFrom' => 'pricing.headline'],
                    ['key' => 'priced_on', 'type' => 'textarea', 'label' => 'This is priced on the outcome my client gets, which is', 'prefillFrom' => 'pricing.value_reason'],
                    ['key' => 'confidence', 'type' => 'checklist', 'label' => 'Confidence checklist',
                     'items' => [
                        ['key' => 'c_outcome', 'label' => 'I named the outcome my client gets'],
                        ['key' => 'c_band', 'label' => 'I picked my band and placed myself in it'],
                        ['key' => 'c_fee', 'label' => 'I set a fee-aware price'],
                        ['key' => 'c_reason', 'label' => 'I wrote my one-line reason'],
                        ['key' => 'c_outloud', 'label' => 'I said my price out loud'],
                     ]],
                ],
            ],
        ],
    ],

    // ══ GATE 3, TOOL 2 ═══════════════════════════════════════════════════
    'first-launch-checklist' => [
        'gate' => 3,
        'title' => 'First Launch Mini-Checklist',
        'lede' => 'Your first launch, minus the guesswork. Three phases, nothing you do not need.',
        'scoring' => ['type' => 'none'],
        'result' => 'launch-plan',
        'wyzai_prompt' =>
            "Act as my launch coach. I am a first-time creator running my first paid launch. My offer is: [what you sell]. It helps "
            . "[who it is for] to [the outcome]. My audience right now is: [where they are and roughly how many]. Give me a simple, "
            . "right-sized launch plan in three phases: before launch, launch week, and after launch. For each phase, give me 3 to 5 "
            . "short action steps and one message I could send. Keep it lightweight for a first launch.",
        'writesToProfile' => [
            'launch.one_person' => 'one_person', 'launch.target_sales' => 'target_sales',
        ],
        'steps' => [
            [
                'title' => 'Who and what',
                'help' => 'Your Gate 1 and Gate 2 work carries over. Edit to fit this launch.',
                'fields' => [
                    ['key' => 'one_person', 'type' => 'textarea', 'required' => true, 'label' => 'One person and one problem this launch is for', 'prefillFrom' => ['clarity.learner_role', 'avatar.role']],
                    ['key' => 'the_offer', 'type' => 'text', 'label' => 'What I am launching', 'prefillFrom' => 'offer.name'],
                ],
            ],
            [
                'title' => 'Phase 1: Before you launch',
                'help' => 'The part that prevents crickets. Warmth beats size.',
                'fields' => [
                    ['key' => 'before', 'type' => 'checklist', 'label' => 'Tick as you finish',
                     'items' => [
                        ['key' => 'b_person', 'label' => 'Named my one person and one problem'],
                        ['key' => 'b_validate', 'label' => 'Validated before building (talked to real buyers)'],
                        ['key' => 'b_presell', 'label' => 'Pre-sold to prove demand (a few early yeses)'],
                        ['key' => 'b_warm', 'label' => 'Warmed my audience with value and proof'],
                        ['key' => 'b_math', 'label' => 'Set realistic launch math (audience, conversion, target)'],
                        ['key' => 'b_essentials', 'label' => 'Prepared the essentials only (page, PH payment, description)'],
                        ['key' => 'b_emotions', 'label' => 'Prepared emotionally for a slow start'],
                     ]],
                ],
            ],
            [
                'title' => 'Your launch math',
                'help' => 'A small first launch counts as a win.',
                'fields' => [
                    ['key' => 'audience_size', 'type' => 'text', 'label' => 'My warm audience size (roughly)'],
                    ['key' => 'conversion_rate', 'type' => 'text', 'label' => 'A modest conversion rate', 'placeholder' => 'e.g. 2 to 5 percent'],
                    ['key' => 'target_sales', 'type' => 'text', 'label' => 'My target number of first sales'],
                ],
            ],
            [
                'title' => 'Phase 2: Launch day and week',
                'help' => 'One announcement is not a launch. A short, steady sequence is.',
                'fields' => [
                    ['key' => 'during', 'type' => 'checklist', 'label' => 'Tick as you finish',
                     'items' => [
                        ['key' => 'd_open', 'label' => 'Open with a clear announcement (offer, outcome, price, deadline)'],
                        ['key' => 'd_value', 'label' => 'Follow with value and proof, not just repetition'],
                        ['key' => 'd_objections', 'label' => 'Answer objections openly'],
                        ['key' => 'd_sequence', 'label' => 'Use a short sequence, not one message'],
                        ['key' => 'd_urgency', 'label' => 'Add honest urgency near close (tied to the real deadline)'],
                     ]],
                ],
            ],
            [
                'title' => 'Phase 3: After you launch',
                'help' => 'Do not go silent. The audience you warmed is your biggest asset for next time.',
                'fields' => [
                    ['key' => 'after', 'type' => 'checklist', 'label' => 'Tick as you finish',
                     'items' => [
                        ['key' => 'a_onboard', 'label' => 'Thanked and onboarded my buyers'],
                        ['key' => 'a_almost', 'label' => 'Followed up the "almost" buyers'],
                        ['key' => 'a_feedback', 'label' => 'Asked for feedback'],
                        ['key' => 'a_nurture', 'label' => 'Kept nurturing my audience'],
                        ['key' => 'a_debrief', 'label' => 'Debriefed and celebrated my first yes'],
                     ]],
                ],
            ],
        ],
    ],

    // ══ GATE 3, TOOL 3 ═══════════════════════════════════════════════════
    'discovery-call-script' => [
        'gate' => 3,
        'title' => 'Discovery Call Script',
        'lede' => 'Turn conversations into clients without feeling salesy. A six-stage call flow.',
        'scoring' => ['type' => 'none'],
        'result' => 'call-script',
        'wyzai_prompt' =>
            "Act as an ethical, consultative sales coach for a [your niche] coach or consultant in the Philippines. Using a "
            . "diagnose-before-you-prescribe approach, help me rewrite these discovery call questions in my own voice for a client "
            . "whose main goal is [their goal]. Keep it warm and non-pushy, and give me one honest way to say the price out loud. "
            . "Here are my current notes: [paste your notes].",
        'writesToProfile' => [
            'script.prescription' => 'prescribe', 'script.price_sentence' => 'price_sentence',
        ],
        'steps' => [
            [
                'title' => 'What you sell',
                'help' => 'This is a diagnosis, not a pitch. Six stages, about 20 to 30 minutes.',
                'fields' => [
                    ['key' => 'what_i_sell', 'type' => 'radio', 'required' => true, 'label' => 'What I sell',
                     'options' => ['Coaching', 'Consulting', 'Done-for-you', 'Other']],
                ],
            ],
            [
                'title' => 'Stage 0: Before the call',
                'help' => 'Send an intake so you walk in informed and screen out people who are not ready.',
                'fields' => [
                    ['key' => 'intake_questions', 'type' => 'textarea', 'required' => true, 'label' => 'My three intake questions', 'rows' => 4,
                     'placeholder' => "What is your main challenge right now?\nWhat do you want to achieve in the next 12 months?\nHow ready are you to invest in solving this?"],
                ],
            ],
            [
                'title' => 'Stages 1 and 2: Connect and Frame',
                'fields' => [
                    ['key' => 'opener', 'type' => 'textarea', 'label' => 'My go-to opener', 'placeholder' => '"I saw [detail from their form or profile]. Tell me a bit about that."'],
                    ['key' => 'framing', 'type' => 'textarea', 'label' => 'My framing line, in my own words', 'placeholder' => '"First I will understand your situation, then what you want, then if it fits I will share how I can help. If it is not a fit, I will tell you honestly."'],
                ],
            ],
            [
                'title' => 'Stage 3: Diagnose',
                'help' => 'The heart of the call. Listen 70 percent. Move from current struggle, to desired vision, to the cost of staying stuck.',
                'fields' => [
                    ['key' => 'diagnosis_q1', 'type' => 'text', 'label' => 'My best diagnosis question 1'],
                    ['key' => 'diagnosis_q2', 'type' => 'text', 'label' => 'My best diagnosis question 2'],
                ],
            ],
            [
                'title' => 'Stages 4 and 5: Prescribe and Invite',
                'help' => 'Prescribe only on a genuine fit. Say the price plainly, then breathe.',
                'fields' => [
                    ['key' => 'prescribe', 'type' => 'textarea', 'required' => true, 'label' => 'My one-line prescription', 'placeholder' => 'The fastest path to [their goal] is [your program].'],
                    ['key' => 'price_sentence', 'type' => 'text', 'required' => true, 'label' => 'My exact price sentence', 'placeholder' => '"The investment is PHP ___. How does that feel to you?"', 'prefillFrom' => 'pricing.final_price'],
                    ['key' => 'feared_objection', 'type' => 'text', 'label' => 'The objection I fear most, and my planned response'],
                ],
            ],
            [
                'title' => 'Stage 6: Follow-up',
                'help' => 'Most buyers convert on the fourth or fifth touch. The sale is often in the follow-up.',
                'fields' => [
                    ['key' => 'followup', 'type' => 'textarea', 'label' => 'My follow-up cadence', 'placeholder' => 'Recap same day, then check in on [day].'],
                ],
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
