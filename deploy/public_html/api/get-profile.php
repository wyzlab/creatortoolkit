<?php
/**
 * GET /api/get-profile.php?tool_slug=...  ->
 *   { profile_version, prefill:{field:{value,source_tool,is_stale}}, answers, current_step }
 * Loads saved progress and carry-forward prefill for one tool.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';
require_once __DIR__ . '/../inc/toolengine.php';

$user = api_require_login();
$uid  = (int)$user['id'];

$slug = (string)($_GET['tool_slug'] ?? '');
$def  = tool_def($slug);
$reg  = tool($slug);
if (!$def || !$reg) {
    fail('Unknown tool.', 404);
}
api_require_gate($uid, (int)$def['gate']);

// Profile
$pf = db()->prepare('SELECT profile_json, version FROM user_profile WHERE user_id = ? LIMIT 1');
$pf->execute([$uid]);
$prow = $pf->fetch();
$profile = $prow ? (json_decode((string)$prow['profile_json'], true) ?: []) : [];
$version = $prow ? (int)$prow['version'] : 1;

// Saved answers for this tool
$ts = db()->prepare('SELECT answers_json, current_step FROM tool_sessions WHERE user_id = ? AND tool_slug = ? LIMIT 1');
$ts->execute([$uid, $slug]);
$srow = $ts->fetch();
$answers = $srow ? (json_decode((string)$srow['answers_json'], true) ?: []) : [];
$currentStep = $srow ? (int)$srow['current_step'] : 1;

$prefill = build_prefill($slug, $def, $profile, $answers);

json_out([
    'profile_version' => $version,
    'prefill' => $prefill,
    'answers' => $answers,
    'current_step' => $currentStep,
]);
