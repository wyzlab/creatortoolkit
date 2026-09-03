<?php
/**
 * POST /api/complete-gate.php  {gate_number}
 *   -> { ok, summary, ai_paragraph, wyzai_code, coach_name, next_gate_unlocked }
 *
 * Ensures a gate whose tools are all done is closed, and returns its summary
 * and WyzAI handover. Idempotent (never burns a second slot). complete-tool
 * already closes a gate when its last tool finishes; this endpoint backs the
 * gate summary page and any manual re-fetch.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';
require_once __DIR__ . '/../inc/gates.php';

$user = api_require_login();
$uid  = (int)$user['id'];

require_post();
csrf_check();

$in = json_input();
$gate = (int)($in['gate_number'] ?? 0);
if (!isset(GATES[$gate])) fail('Unknown gate.', 404);
api_require_gate($uid, $gate);

$pdo = db();
try {
    $pdo->beginTransaction();
    $handover = close_gate($pdo, $uid, $gate);
    $pdo->commit();
} catch (\Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('complete-gate failed: ' . $ex->getMessage());
    fail('We could not close that gate just now. Please try again.', 500);
}

if ($handover === null) {
    fail('That gate is not complete yet.', 409);
}

// Post-commit: AI coach note + gate email (never inside the transaction).
$handover = finalize_gate_after_commit($pdo, $uid, $gate, $handover);

json_out([
    'ok' => true,
    'summary' => $handover['summary_html'],
    'ai_paragraph' => $handover['ai_paragraph'],
    'wyzai_code' => $handover['wyzai_code'],
    'coach_name' => $handover['coach_name'],
    'next_gate_unlocked' => $handover['next_gate_unlocked'],
]);
