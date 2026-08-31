<?php
/**
 * GET /api/admin/stats.php  ->  overview numbers for the admin dashboard.
 * Admin only. The drop-off view (started vs completed per tool) is the single
 * most useful number: it shows which tool is losing people.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/guard.php';
require_once __DIR__ . '/../../inc/codes.php';
require_once __DIR__ . '/../../inc/tools.php';

api_require_admin();
$pdo = db();

$buyers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='learner'")->fetchColumn();

// Gate completion counts.
$gates = [];
foreach (GATES as $n => $g) {
    $done = (int)$pdo->query("SELECT COUNT(*) FROM gate_progress WHERE gate_number=$n AND completed_at IS NOT NULL")->fetchColumn();
    $gates[] = ['gate' => $n, 'label' => $g['label'], 'completed' => $done];
}

// Drop-off by tool: started vs completed.
$dropoff = [];
$s = $pdo->prepare("SELECT
        SUM(1) started,
        SUM(status='completed') completed
      FROM tool_sessions WHERE tool_slug = ?");
foreach (TOOLS as $slug => $t) {
    $s->execute([$slug]);
    $r = $s->fetch() ?: ['started' => 0, 'completed' => 0];
    $dropoff[] = [
        'slug' => $slug, 'title' => $t['title'], 'gate' => $t['gate'],
        'started' => (int)($r['started'] ?? 0),
        'completed' => (int)($r['completed'] ?? 0),
    ];
}

$failedEmails = (int)$pdo->query("SELECT COUNT(*) FROM email_log WHERE status='failed'")->fetchColumn();
$queuedEmails = (int)$pdo->query("SELECT COUNT(*) FROM email_log WHERE status='queued'")->fetchColumn();
$failedAi     = (int)$pdo->query("SELECT COUNT(*) FROM ai_log WHERE status='failed'")->fetchColumn();

json_out([
    'buyers' => $buyers,
    'gates' => $gates,
    'dropoff' => $dropoff,
    'codes' => code_counts($pdo),
    'emails' => ['failed' => $failedEmails, 'queued' => $queuedEmails],
    'ai' => ['failed' => $failedAi],
]);
