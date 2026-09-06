<?php
/**
 * codes.php — mint access codes from the web side (admin UI, purchase webhook).
 * Shares the same format and HMAC storage as tools/gen-codes.php (the CLI).
 * Sign-in verifies against the HMAC lookup (code_lookup); the readable code is
 * also kept in code_display so the admin can hand it out from the console.
 */

declare(strict_types=1);

/** One readable code, three groups of three from an unambiguous alphabet. */
function make_code_string(): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';  // no I, L, O, 0, 1
    $alen = strlen($alphabet);
    $groups = [];
    for ($g = 0; $g < 3; $g++) {
        $s = '';
        for ($i = 0; $i < 3; $i++) {
            $s .= $alphabet[random_int(0, $alen - 1)];
        }
        $groups[] = $s;
    }
    return implode('-', $groups);
}

/**
 * Mint $count codes into access_codes and return the plaintext list.
 * If $issuedEmail is set, the codes are tagged to that buyer.
 *
 * @return string[] the plaintext codes (show once, cannot be recovered later)
 */
function mint_codes(PDO $pdo, int $count, string $batch = 'admin', ?string $issuedEmail = null): array
{
    $count = max(1, min($count, 500));   // sane bounds
    $ins = $pdo->prepare(
        'INSERT INTO access_codes (code_lookup, code_display, batch_label, issued_to_email, status, created_at)
         VALUES (?, ?, ?, ?, "unclaimed", ?)'
    );
    $now = now_dt();
    $made = [];
    $attempts = 0;
    while (count($made) < $count && $attempts < $count * 20) {
        $attempts++;
        $code = make_code_string();
        $lookup = code_lookup($code);
        // Keep the full code so the admin can always read it back in the
        // "Recent codes" table (these are free beta access codes handed out by
        // hand). Sign-in still verifies against the HMAC in code_lookup.
        $display = $code;
        try {
            $ins->execute([$lookup, $display, $batch, $issuedEmail, $now]);
            $made[] = $code;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') { throw $e; }   // ignore rare collisions
        }
    }
    return $made;
}

/**
 * Is the code_redemptions table present yet? Recording a use is best-effort:
 * on a live DB the table arrives via a manual migration, and until then sign-up
 * must not break, so callers skip recording when this is false. Cached.
 */
function code_redemptions_supported(PDO $pdo): bool
{
    static $has = null;
    if ($has !== null) { return $has; }
    try {
        $has = (bool)$pdo->query("SHOW TABLES LIKE 'code_redemptions'")->fetchColumn();
    } catch (\Throwable $e) {
        $has = false;
    }
    return $has;
}

/**
 * Record that an access code was used to set up an account. One row per use, so
 * a shared universal code accrues a row per sign-up. No-ops (safely) until the
 * table exists. Call inside the sign-up transaction.
 */
function record_redemption(PDO $pdo, int $codeId, int $userId, string $email, ?string $batchLabel): void
{
    if (!code_redemptions_supported($pdo)) { return; }
    $pdo->prepare(
        'INSERT INTO code_redemptions (code_id, user_id, email, batch_label, redeemed_at)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$codeId, $userId, $email, $batchLabel, now_dt()]);
}

/** How many times a universal (shared) code has been used to sign up. */
function universal_use_count(PDO $pdo): int
{
    if (!code_redemptions_supported($pdo)) { return 0; }
    $s = $pdo->query("SELECT COUNT(*) FROM code_redemptions WHERE batch_label = '__universal__'");
    return (int)$s->fetchColumn();
}

/** Each universal-code sign-up: email and when, newest first. */
function universal_redemptions(PDO $pdo, int $limit = 500): array
{
    if (!code_redemptions_supported($pdo)) { return []; }
    $limit = max(1, min($limit, 2000));
    $s = $pdo->query(
        "SELECT email, redeemed_at FROM code_redemptions
          WHERE batch_label = '__universal__'
          ORDER BY redeemed_at DESC, id DESC
          LIMIT $limit"
    );
    return $s->fetchAll();
}

/**
 * Redemption count per code_id, for a given set of code ids. Empty when the
 * table is absent or no ids are given. Used by the Recent codes table so a
 * shared code shows how many sign-ups it produced.
 */
function redemption_counts_by_code(PDO $pdo, array $codeIds): array
{
    if (!$codeIds || !code_redemptions_supported($pdo)) { return []; }
    $ids = array_values(array_unique(array_map('intval', $codeIds)));
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $s = $pdo->prepare("SELECT code_id, COUNT(*) c FROM code_redemptions
                         WHERE code_id IN ($in) GROUP BY code_id");
    $s->execute($ids);
    $out = [];
    foreach ($s->fetchAll() as $r) { $out[(int)$r['code_id']] = (int)$r['c']; }
    return $out;
}

/**
 * Every universal (shared) code, newest first, each with its own list of
 * sign-ups. Grouping by the specific code lets the admin keep the users of one
 * code separate from another after a rotation.
 *
 * @return array<int,array{code_id:int,code:string,status:string,created_at:string,count:int,uses:array}>
 */
function universal_codes_with_uses(PDO $pdo): array
{
    $codes = $pdo->query(
        "SELECT id, code_display, status, created_at FROM access_codes
          WHERE batch_label = '__universal__' ORDER BY id DESC"
    )->fetchAll();

    $tracked = code_redemptions_supported($pdo);
    $redStmt = $tracked
        ? $pdo->prepare("SELECT email, redeemed_at FROM code_redemptions
                          WHERE code_id = ? ORDER BY redeemed_at DESC, id DESC")
        : null;

    $out = [];
    foreach ($codes as $c) {
        $uses = [];
        if ($redStmt) {
            $redStmt->execute([(int)$c['id']]);
            $uses = array_map(
                fn($r) => ['email' => $r['email'], 'redeemed_at' => $r['redeemed_at']],
                $redStmt->fetchAll()
            );
        }
        $disp = (string)$c['code_display'];
        $out[] = [
            'code_id'    => (int)$c['id'],
            // Older universal codes stored only the last 4; keep them masked.
            'code'       => mb_strlen($disp) > 4 ? $disp : ('****-' . $disp),
            'status'     => (string)$c['status'],
            'created_at' => $c['created_at'],
            'count'      => count($uses),
            'uses'       => $uses,
        ];
    }
    return $out;
}

/** Count codes by status, for the admin view. */
function code_counts(PDO $pdo): array
{
    $out = ['unclaimed' => 0, 'claimed' => 0, 'revoked' => 0, 'expired' => 0];
    $rows = $pdo->query('SELECT status, COUNT(*) c FROM access_codes GROUP BY status')->fetchAll();
    foreach ($rows as $r) { $out[$r['status']] = (int)$r['c']; }
    return $out;
}
