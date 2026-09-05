<?php
/**
 * offers.php — saved One-Page Offers. Each time a learner finishes the One-Page
 * Offer tool, a snapshot is stored here (title, the answers, and the rendered
 * offer card). This powers "My Offers": keep several, print any to PDF, start
 * another. It is additive — the gate journey and its single canonical result
 * are untouched.
 */

declare(strict_types=1);

/** A friendly title for a saved offer, from its answers. */
function offer_title_from_answers(array $answers): string
{
    $name = trim((string)($answers['offer_name'] ?? ''));
    if ($name !== '') { return mb_substr($name, 0, 180); }
    $who = trim((string)($answers['offer_who'] ?? ''));
    if ($who !== '') { return mb_substr('Offer for ' . $who, 0, 180); }
    return 'Untitled offer';
}

/** Snapshot one finished offer. Returns the new offer id. */
function save_offer(PDO $pdo, int $userId, array $answers, array $resultJson, string $resultHtml): int
{
    $now = now_dt();
    $stmt = $pdo->prepare(
        'INSERT INTO offers (user_id, title, answers_json, result_json, result_html, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        offer_title_from_answers($answers),
        json_encode($answers, JSON_UNESCAPED_UNICODE),
        json_encode($resultJson, JSON_UNESCAPED_UNICODE),
        $resultHtml,
        $now, $now,
    ]);
    return (int)$pdo->lastInsertId();
}

/** Replace an existing offer in place (an edit, not a new copy). */
function update_offer(PDO $pdo, int $userId, int $id, array $answers, array $resultJson, string $resultHtml): void
{
    $stmt = $pdo->prepare(
        'UPDATE offers SET title = ?, answers_json = ?, result_json = ?, result_html = ?, updated_at = ?
          WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([
        offer_title_from_answers($answers),
        json_encode($answers, JSON_UNESCAPED_UNICODE),
        json_encode($resultJson, JSON_UNESCAPED_UNICODE),
        $resultHtml,
        now_dt(), $id, $userId,
    ]);
}

/**
 * Does the offers table have the deleted_at column yet? Deleting an offer now
 * moves it to the trash (soft delete) instead of erasing it, so it can be
 * restored. On a live DB the column arrives via a manual migration; until then
 * we fall back to the old hard-delete behaviour so nothing breaks. Cached per
 * request.
 */
function offers_soft_delete_supported(PDO $pdo): bool
{
    static $has = null;
    if ($has !== null) { return $has; }
    try {
        $has = (bool)$pdo->query("SHOW COLUMNS FROM offers LIKE 'deleted_at'")->fetchColumn();
    } catch (\Throwable $e) {
        $has = false;
    }
    return $has;
}

/** SQL fragment that limits to live (not trashed) offers, when supported. */
function offers_live_clause(PDO $pdo): string
{
    return offers_soft_delete_supported($pdo) ? ' AND deleted_at IS NULL' : '';
}

/** Does this LIVE offer id belong to this user? (trashed offers do not count) */
function offer_exists_for_user(PDO $pdo, int $userId, int $id): bool
{
    $s = $pdo->prepare('SELECT 1 FROM offers WHERE id = ? AND user_id = ?' . offers_live_clause($pdo) . ' LIMIT 1');
    $s->execute([$id, $userId]);
    return (bool)$s->fetchColumn();
}

/** How many LIVE offers this user has saved (drives the smart landing). */
function user_offer_count(PDO $pdo, int $userId): int
{
    $s = $pdo->prepare('SELECT COUNT(*) FROM offers WHERE user_id = ?' . offers_live_clause($pdo));
    $s->execute([$userId]);
    return (int)$s->fetchColumn();
}

/** A user's LIVE offers, newest first (list view: no heavy columns). */
function list_offers(PDO $pdo, int $userId): array
{
    $s = $pdo->prepare('SELECT id, title, created_at FROM offers
                         WHERE user_id = ?' . offers_live_clause($pdo) . '
                         ORDER BY created_at DESC, id DESC');
    $s->execute([$userId]);
    return $s->fetchAll();
}

/** A user's trashed offers, most recently deleted first. Empty if unsupported. */
function list_deleted_offers(PDO $pdo, int $userId): array
{
    if (!offers_soft_delete_supported($pdo)) { return []; }
    $s = $pdo->prepare('SELECT id, title, created_at, deleted_at FROM offers
                         WHERE user_id = ? AND deleted_at IS NOT NULL
                         ORDER BY deleted_at DESC, id DESC');
    $s->execute([$userId]);
    return $s->fetchAll();
}

/** One offer belonging to this user, or null. Trashed offers still resolve
 *  (so a restored/print/email flow can reach them by id). */
function get_offer(PDO $pdo, int $userId, int $id): ?array
{
    $s = $pdo->prepare('SELECT id, title, answers_json, result_json, result_html, created_at
                          FROM offers WHERE id = ? AND user_id = ? LIMIT 1');
    $s->execute([$id, $userId]);
    $r = $s->fetch();
    return $r ?: null;
}

/** Delete one offer belonging to this user. Soft delete (to trash) when the
 *  column exists; otherwise a hard delete for backward compatibility. */
function delete_offer(PDO $pdo, int $userId, int $id): void
{
    if (offers_soft_delete_supported($pdo)) {
        $pdo->prepare('UPDATE offers SET deleted_at = ?, updated_at = ? WHERE id = ? AND user_id = ? AND deleted_at IS NULL')
            ->execute([now_dt(), now_dt(), $id, $userId]);
    } else {
        $pdo->prepare('DELETE FROM offers WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
    }
}

/** Bring a trashed offer back to the live list. */
function restore_offer(PDO $pdo, int $userId, int $id): void
{
    if (!offers_soft_delete_supported($pdo)) { return; }
    $pdo->prepare('UPDATE offers SET deleted_at = NULL, updated_at = ? WHERE id = ? AND user_id = ?')
        ->execute([now_dt(), $id, $userId]);
}

/** Erase one offer for good (used by "delete permanently" from the trash). */
function purge_offer(PDO $pdo, int $userId, int $id): void
{
    $pdo->prepare('DELETE FROM offers WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
}
