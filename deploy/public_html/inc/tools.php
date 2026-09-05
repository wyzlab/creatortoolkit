<?php
/**
 * tools.php — the ten-tool registry. Non-secret reference data.
 *
 * Reconstructed from the published source PDFs (product ids come from each
 * PDF's Proof of Authorship checkout link) and the Technical Spec gate map.
 * The 20-Point Offer example in the Technical Spec (product 14639, One-Page
 * Offer, "July 28, 2026") matches row 4 below, confirming the mapping.
 *
 * gate wording is the learner-facing label; the internal key is what the
 * code and the database use.
 */

declare(strict_types=1);

const GATES = [
    1 => ['key' => 'clarity',     'label' => 'Get Clear',           'tools_required' => 3],
    // Gate 2 has 5 tool cards but 4 required slots: the two Sparkers (course and
    // digital product) are a "pick one" pair that together count as one slot.
    2 => ['key' => 'creation',    'label' => 'Build Your Offer',    'tools_required' => 4,
          'choose_one' => [['content-to-course-sparker', 'content-to-digital-product-sparker']]],
    3 => ['key' => 'credibility', 'label' => 'Price, Launch, Sell', 'tools_required' => 3],
];

/** "Pick one" groups in a gate: completing ANY tool in a group fills one slot. */
function gate_choose_one_groups(int $gate): array
{
    return GATES[$gate]['choose_one'] ?? [];
}

/** Every slug that belongs to a pick-one group in a gate. */
function gate_choose_one_slugs(int $gate): array
{
    $out = [];
    foreach (gate_choose_one_groups($gate) as $group) {
        foreach ($group as $slug) { $out[] = $slug; }
    }
    return $out;
}

/**
 * Order within each gate is the recommended dashboard order.
 * Gate 1 deliberately flips the published order (Avatar first) so the
 * Clarity Framework arrives pre-filled.
 */
const TOOLS = [
    // ── Gate 1: Get Clear ────────────────────────────────────────────────
    'ideal-client-avatar' => [
        'gate' => 1, 'order' => 1,
        'title' => 'Ideal Client Avatar Kit',
        'product_id' => 14636, 'published_on' => 'July 28, 2026', 'version' => '1.1',
        'pdf' => '01_ideal-client-avatar.pdf',
    ],
    'course-clarity-framework' => [
        'gate' => 1, 'order' => 2,
        'title' => 'Course Clarity Framework',
        'product_id' => 14578, 'published_on' => 'July 16, 2026', 'version' => '1.1',
        'pdf' => '02_course-clarity-framework.pdf',
    ],
    'course-idea-validation' => [
        'gate' => 1, 'order' => 3,
        'title' => 'Will It Sell? Validation Check',
        'product_id' => 14642, 'published_on' => 'July 28, 2026', 'version' => '1.1',
        'pdf' => '03_course-idea-validation.pdf',
    ],

    // ── Gate 2: Build Your Offer ─────────────────────────────────────────
    'one-page-offer' => [
        'gate' => 2, 'order' => 1,
        'title' => 'One-Page Offer Template',
        'product_id' => 14639, 'published_on' => 'July 28, 2026', 'version' => '1.1',
        'pdf' => '04_one-page-offer.pdf',
    ],
    'content-to-course-sparker' => [
        'gate' => 2, 'order' => 2,
        'title' => 'Content-to-Course Idea Sparker',
        'product_id' => 14641, 'published_on' => 'July 28, 2026', 'version' => '1.1',
        'pdf' => '05_content-to-course-sparker.pdf',
    ],
    // Pick-one sibling of the Course Sparker (digital product). Real build and
    // PDF are in place; product_id is still a placeholder until the WyzCore
    // checkout link exists (only surfaced inside the PDF, not on screen).
    'content-to-digital-product-sparker' => [
        'gate' => 2, 'order' => 2,
        'title' => 'Content-to-Product Idea Sparker',
        'product_id' => 0, 'published_on' => '', 'version' => '1.0',
        'pdf' => '11_content-to-product-sparker.pdf',
    ],
    'course-design-checklist' => [
        'gate' => 2, 'order' => 3,
        'title' => '20-Point Course Design Checklist',
        'product_id' => 14540, 'published_on' => 'July 8, 2026', 'version' => '1.1',
        'pdf' => '06_course-design-checklist.pdf',
    ],
    'filipino-creators-starter-kit' => [
        'gate' => 2, 'order' => 4,
        'title' => "Filipino Creator's Starter Kit",
        'product_id' => 14579, 'published_on' => 'July 16, 2026', 'version' => '1.1',
        'pdf' => '07_filipino-creators-starter-kit.pdf',
    ],

    // ── Gate 3: Price, Launch, Sell ──────────────────────────────────────
    'pricing-confidence' => [
        'gate' => 3, 'order' => 1,
        'title' => 'Pricing Confidence Starter',
        'product_id' => 14637, 'published_on' => 'July 28, 2026', 'version' => '1.1',
        'pdf' => '08_pricing-confidence.pdf',
    ],
    'first-launch-checklist' => [
        'gate' => 3, 'order' => 2,
        'title' => 'First Launch Mini-Checklist',
        'product_id' => 14638, 'published_on' => 'July 28, 2026', 'version' => '1.1',
        'pdf' => '09_first-launch-checklist.pdf',
    ],
    'discovery-call-script' => [
        'gate' => 3, 'order' => 3,
        'title' => 'Discovery Call Script',
        'product_id' => 14640, 'published_on' => 'July 28, 2026', 'version' => '1.1',
        'pdf' => '10_discovery-call-script.pdf',
    ],
];

/** Tools in a gate, in recommended order. */
function tools_in_gate(int $gate): array
{
    $out = [];
    foreach (TOOLS as $slug => $t) {
        if ($t['gate'] === $gate) {
            $out[$slug] = $t;
        }
    }
    uasort($out, fn($a, $b) => $a['order'] <=> $b['order']);
    return $out;
}

/** Look up one tool, or null. */
function tool(string $slug): ?array
{
    return TOOLS[$slug] ?? null;
}
