<?php
/**
 * GET /api/download-pdf.php?tool_slug=...  ->  the file, or 403
 *
 * The ONLY route to a source PDF. The files sit in /private/assets above the
 * web root. This checks the session and the pdf_unlocks table, increments the
 * counter, and streams the file. .htaccess blocks any direct path.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';

// A file endpoint, so failures are plain HTTP, not JSON.
if (!is_logged_in()) {
    http_response_code(403);
    exit('Please log in.');
}
$user = current_user();
$uid  = (int)$user['id'];

$slug = (string)($_GET['tool_slug'] ?? '');
$reg  = tool($slug);
if (!$reg) { http_response_code(404); exit('Unknown tool.'); }

// Must be unlocked for this user.
$stmt = db()->prepare('SELECT id FROM pdf_unlocks WHERE user_id = ? AND tool_slug = ? LIMIT 1');
$stmt->execute([$uid, $slug]);
if (!$stmt->fetch()) { http_response_code(403); exit('That download is still locked.'); }

// Resolve the file safely (no path from user input).
$dir  = rtrim((string)APP['pdf_dir'], '/');
$file = $dir . '/' . basename((string)$reg['pdf']);
$real = realpath($file);
if ($real === false || strpos($real, realpath($dir)) !== 0 || !is_file($real)) {
    http_response_code(404);
    exit('File not found.');
}

// Count the download.
db()->prepare('UPDATE pdf_unlocks SET download_count = download_count + 1, last_downloaded_at = ?
               WHERE user_id = ? AND tool_slug = ?')->execute([now_dt(), $uid, $slug]);

// Stream it.
$downloadName = $reg['title'] . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
header('Content-Length: ' . filesize($real));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($real);
exit;
