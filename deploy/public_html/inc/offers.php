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

/** All of a user's offers, newest first (list view: no heavy columns). */
function list_offers(PDO $pdo, int $userId): array
{
    $s = $pdo->prepare('SELECT id, title, created_at FROM offers
                         WHERE user_id = ? ORDER BY created_at DESC, id DESC');
    $s->execute([$userId]);
    return $s->fetchAll();
}

/** One offer belonging to this user, or null. */
function get_offer(PDO $pdo, int $userId, int $id): ?array
{
    $s = $pdo->prepare('SELECT id, title, answers_json, result_json, result_html, created_at
                          FROM offers WHERE id = ? AND user_id = ? LIMIT 1');
    $s->execute([$id, $userId]);
    $r = $s->fetch();
    return $r ?: null;
}

/** Delete one offer belonging to this user. */
function delete_offer(PDO $pdo, int $userId, int $id): void
{
    $pdo->prepare('DELETE FROM offers WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
}
