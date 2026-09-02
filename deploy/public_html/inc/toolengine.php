<?php
/**
 * toolengine.php — server-side support for the tools: required-field checks,
 * carry-forward prefill, writing answers back into the shared profile, and
 * the stale-field propagation that powers "you changed X, update this?".
 *
 * The profile object (user_profile.profile_json) is namespaced by tool group,
 * for example { "avatar": {...}, "clarity": {...}, "validation": {...},
 * "_stale": { "course-clarity-framework.learner_pain": true } }.
 */

declare(strict_types=1);

require_once __DIR__ . '/tooldefs.php';

/** Every field across every step, flattened. */
function td_fields(array $def): array
{
    $out = [];
    foreach ($def['steps'] as $step) {
        foreach ($step['fields'] as $f) {
            $out[$f['key']] = $f;
        }
    }
    return $out;
}

/** Keys of required fields. */
function td_required_keys(array $def): array
{
    $keys = [];
    foreach (td_fields($def) as $key => $f) {
        if (!empty($f['required'])) { $keys[] = $key; }
    }
    return $keys;
}

/** True when a value counts as empty (string, or checklist with nothing ticked). */
function value_empty($v): bool
{
    if ($v === null) return true;
    if (is_string($v)) return trim($v) === '';
    if (is_array($v)) {
        foreach ($v as $x) { if ($x) return false; }
        return true;
    }
    return false;
}

/** Dot-path get from a nested array. */
function profile_get(array $profile, string $path)
{
    $node = $profile;
    foreach (explode('.', $path) as $k) {
        if (!is_array($node) || !array_key_exists($k, $node)) return null;
        $node = $node[$k];
    }
    return $node;
}

/** Dot-path set into a nested array (by reference). */
function profile_set(array &$profile, string $path, $value): void
{
    $keys = explode('.', $path);
    $node = &$profile;
    foreach ($keys as $i => $k) {
        if ($i === count($keys) - 1) {
            $node[$k] = $value;
        } else {
            if (!isset($node[$k]) || !is_array($node[$k])) { $node[$k] = []; }
            $node = &$node[$k];
        }
    }
}

/** Resolve the first non-empty prefill source for a field. Returns [value, sourceTool] or [null, null]. */
function resolve_prefill(array $profile, $prefillFrom): array
{
    $paths = is_array($prefillFrom) ? $prefillFrom : [$prefillFrom];
    foreach ($paths as $p) {
        $v = profile_get($profile, $p);
        if (!value_empty($v)) {
            $group = explode('.', $p)[0];
            return [$v, $group];
        }
    }
    return [null, null];
}

/**
 * Build the prefill map the client needs, for one tool.
 * Returns [ field_key => { value, source_tool, is_stale } ] for fields that
 * carry forward. is_stale is only true when the field already holds an answer
 * (so it is a real "update this to match?" moment), not on a fresh open.
 */
function build_prefill(string $slug, array $def, array $profile, array $answers): array
{
    $stale = $profile['_stale'] ?? [];
    $out = [];
    foreach (td_fields($def) as $key => $f) {
        if (empty($f['prefillFrom'])) continue;
        [$val, $src] = resolve_prefill($profile, $f['prefillFrom']);
        $answered = isset($answers[$key]) && !value_empty($answers[$key]);
        $isStale = !empty($stale["$slug.$key"]) && $answered;
        $out[$key] = [
            'value' => $val,
            'source_tool' => $src,
            'is_stale' => $isStale,
        ];
    }
    return $out;
}

/** Index: profile source path => list of [downstreamSlug, fieldKey] that prefill from it. */
function prefill_dependency_index(): array
{
    static $idx = null;
    if ($idx !== null) return $idx;
    $idx = [];
    foreach (tool_defs() as $slug => $def) {
        foreach (td_fields($def) as $key => $f) {
            if (empty($f['prefillFrom'])) continue;
            $paths = is_array($f['prefillFrom']) ? $f['prefillFrom'] : [$f['prefillFrom']];
            foreach ($paths as $p) {
                $idx[$p][] = [$slug, $key];
            }
        }
    }
    return $idx;
}

/**
 * Write a tool's answers into the shared profile and propagate staleness.
 * When an already-set source value changes, downstream fields that carried it
 * are marked stale so the learner is asked before anything is overwritten.
 */
function apply_writes_to_profile(array &$profile, string $slug, array $def, array $answers): void
{
    if (empty($def['writesToProfile'])) return;
    $idx = prefill_dependency_index();

    foreach ($def['writesToProfile'] as $path => $fieldKey) {
        if (!array_key_exists($fieldKey, $answers)) continue;
        $newVal = $answers[$fieldKey];
        $oldVal = profile_get($profile, $path);
        profile_set($profile, $path, $newVal);

        // Only an edit to a previously non-empty value propagates staleness.
        $changed = json_encode($oldVal) !== json_encode($newVal);
        if ($changed && !value_empty($oldVal) && isset($idx[$path])) {
            if (!isset($profile['_stale']) || !is_array($profile['_stale'])) {
                $profile['_stale'] = [];
            }
            foreach ($idx[$path] as [$dslug, $dkey]) {
                if ($dslug === $slug) continue;   // never mark your own field
                // Flat key, matching build_prefill() and clear_stale_for().
                $profile['_stale']["$dslug.$dkey"] = true;
            }
        }
    }
}

/** Clear stale flags for fields the learner just filled with a value. */
function clear_stale_for(array &$profile, string $slug, array $answers): void
{
    if (empty($profile['_stale'])) return;
    foreach ($answers as $key => $val) {
        if (!value_empty($val)) {
            unset($profile['_stale']["$slug.$key"]);
        }
    }
}

/** Validation Check verdict: GO / REFINE / RESET / PENDING, plus the yes count. */
function compute_validation_verdict(array $answers): array
{
    $signals = $answers['signals'] ?? [];
    $yes = 0;
    if (is_array($signals)) {
        foreach ($signals as $on) { if ($on) $yes++; }
    }

    $commit = $answers['commitments_number'] ?? '';
    $threshold = $answers['threshold_number'] ?? '';

    if ($commit === '' || $commit === null) {
        $verdict = 'PENDING';
    } else {
        $c = (int)$commit;
        $t = ($threshold === '' || $threshold === null) ? null : (int)$threshold;
        if ($t !== null && $c >= $t) {
            $verdict = 'GO';
        } elseif ($c > 0) {
            $verdict = 'REFINE';
        } else {
            $verdict = 'RESET';
        }
    }
    return ['verdict' => $verdict, 'yes_count' => $yes];
}

/**
 * 20-Point Checklist score. Points 1 to 19 score one each (checkboxes cp1..cp19
 * across zones A to G). Point 20 scores one only when 20a, 20b, 20c and 20d all
 * tick (the zoneH checklist). Maximum 20. Returns [score, band].
 */
function compute_checklist_score(array $answers): array
{
    $score = 0;
    // Points 1 to 19 live in the zone A..G checklist fields.
    foreach (['zoneA', 'zoneB', 'zoneC', 'zoneD', 'zoneE', 'zoneF', 'zoneG'] as $zone) {
        $set = $answers[$zone] ?? [];
        if (is_array($set)) {
            foreach ($set as $on) { if ($on) $score++; }
        }
    }
    // Point 20: all four of 20a-d must tick.
    $h = $answers['zoneH'] ?? [];
    $point20 = is_array($h)
        && !empty($h['p20a']) && !empty($h['p20b']) && !empty($h['p20c']) && !empty($h['p20d']);
    if ($point20) { $score++; }
    $score = min($score, 20);

    if ($score >= 16)      $band = 'ready';
    elseif ($score >= 11)  $band = 'almost';
    elseif ($score >= 6)   $band = 'building';
    else                   $band = 'clarity';

    return ['score' => $score, 'band' => $band];
}

/**
 * Tool-specific derived profile values, run after apply_writes_to_profile.
 * The Idea Sparker's separate module fields become one "sparker.modules" block
 * so the 20-Point Checklist's Zone C can carry them forward.
 */
function derive_profile(array &$profile, string $slug, array $answers): void
{
    if ($slug === 'content-to-course-sparker') {
        $mods = [];
        foreach (['module_1', 'module_2', 'module_3', 'module_4', 'module_5'] as $k) {
            if (!value_empty($answers[$k] ?? null)) { $mods[] = (string)$answers[$k]; }
        }
        if ($mods) { profile_set($profile, 'sparker.modules', implode("\n", $mods)); }
    }
}
