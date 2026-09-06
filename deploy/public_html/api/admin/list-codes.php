<?php
/**
 * GET /api/admin/list-codes.php?status=&limit=&offset=  ->  {codes:[...], total}
 * Admin only. Returns each code's readable value (newer codes store the full
 * code so the admin can hand it out; older codes kept only the last 4 and stay
 * masked), plus status, batch, and who they were issued to or claimed by.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/guard.php';
require_once __DIR__ . '/../../inc/codes.php';

api_require_admin();
$pdo = db();

$status = (string)($_GET['status'] ?? '');
$limit  = max(1, min((int)($_GET['limit'] ?? 50), 200));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$where = '';
$params = [];
if (in_array($status, ['unclaimed', 'claimed', 'revoked', 'expired'], true)) {
    $where = 'WHERE ac.status = ?';
    $params[] = $status;
}

$total = (int)($where
    ? (function () use ($pdo, $params) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM access_codes ac ' . 'WHERE ac.status = ?');
        $q->execute($params);
        return $q->fetchColumn();
      })()
    : $pdo->query('SELECT COUNT(*) FROM access_codes')->fetchColumn());

$sql = "SELECT ac.id, ac.code_display, ac.batch_label, ac.issued_to_email, ac.status,
               ac.created_at, ac.claimed_at, u.email AS claimed_email
          FROM access_codes ac
          LEFT JOIN users u ON u.id = ac.claimed_by_user_id
          $where
         ORDER BY ac.id DESC
         LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// How many sign-ups each code produced (a shared universal code accrues many).
$useCounts = redemption_counts_by_code($pdo, array_map(fn($r) => (int)$r['id'], $rows));

$codes = [];
foreach ($rows as $r) {
    // Newer codes store the full code (readable); older ones kept only the
    // last 4, which stay masked because the rest was never stored.
    $disp = (string)$r['code_display'];
    $shown = (mb_strlen($disp) > 4) ? $disp : ('****-' . $disp);
    $codes[] = [
        'display' => $shown,
        'batch' => $r['batch_label'],
        'issued_to' => $r['issued_to_email'],
        'status' => $r['status'],
        'claimed_by' => $r['claimed_email'],
        'created_at' => $r['created_at'],
        'claimed_at' => $r['claimed_at'],
        'is_universal' => ($r['batch_label'] === '__universal__'),
        'uses' => (int)($useCounts[(int)$r['id']] ?? 0),
    ];
}

json_out(['codes' => $codes, 'total' => $total, 'limit' => $limit, 'offset' => $offset]);
