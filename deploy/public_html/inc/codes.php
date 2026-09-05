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

/** Count codes by status, for the admin view. */
function code_counts(PDO $pdo): array
{
    $out = ['unclaimed' => 0, 'claimed' => 0, 'revoked' => 0, 'expired' => 0];
    $rows = $pdo->query('SELECT status, COUNT(*) c FROM access_codes GROUP BY status')->fetchAll();
    foreach ($rows as $r) { $out[$r['status']] = (int)$r['c']; }
    return $out;
}
