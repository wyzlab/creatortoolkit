<?php
/**
 * GET /api/get-results.php  ->  { gates:[...], tools:[...], profile }
 * A read-only overview of everything the learner has produced. Backs the
 * package page and any results review.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';
require_once __DIR__ . '/../inc/tools.php';

$user = api_require_login();
$uid  = (int)$user['id'];

$gates = [];
$g = db()->prepare('SELECT gate_number, tools_required, tools_completed, unlocked_at, completed_at
                      FROM gate_progress WHERE user_id = ? ORDER BY gate_number');
$g->execute([$uid]);
foreach ($g->fetchAll() as $r) {
    $gr = db()->prepare('SELECT summary_html, ai_paragraph FROM gate_results WHERE user_id = ? AND gate_number = ? LIMIT 1');
    $gr->execute([$uid, (int)$r['gate_number']]);
    $sum = $gr->fetch() ?: [];
    $gates[] = [
        'gate' => (int)$r['gate_number'],
        'label' => GATES[(int)$r['gate_number']]['label'] ?? '',
        'tools_required' => (int)$r['tools_required'],
        'tools_completed' => (int)$r['tools_completed'],
        'unlocked' => $r['unlocked_at'] !== null,
        'completed' => $r['completed_at'] !== null,
        'summary_html' => $sum['summary_html'] ?? null,
        'ai_paragraph' => $sum['ai_paragraph'] ?? null,
    ];
}

$tools = [];
$t = db()->prepare('SELECT tr.tool_slug, tr.result_html, ts.status, ts.completed_at
                      FROM tool_results tr
                      JOIN tool_sessions ts ON ts.user_id = tr.user_id AND ts.tool_slug = tr.tool_slug
                     WHERE tr.user_id = ?');
$t->execute([$uid]);
foreach ($t->fetchAll() as $r) {
    $reg = tool($r['tool_slug']);
    $tools[] = [
        'slug' => $r['tool_slug'],
        'title' => $reg['title'] ?? $r['tool_slug'],
        'gate' => $reg['gate'] ?? null,
        'result_html' => $r['result_html'],
    ];
}

$pf = db()->prepare('SELECT profile_json FROM user_profile WHERE user_id = ? LIMIT 1');
$pf->execute([$uid]);
$profile = json_decode((string)$pf->fetchColumn() ?: '{}', true) ?: [];
unset($profile['_stale']);

json_out(['gates' => $gates, 'tools' => $tools, 'profile' => $profile]);
