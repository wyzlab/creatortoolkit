<?php
/**
 * wyzai.php — claiming a WyzAI coach code for a trigger.
 *
 * Five triggers, one coach each: login (Welcome Buddy), gate_1 (Clarity),
 * gate_2 (Creation), gate_3 (Credibility), package (Community Designer).
 *
 * uq_user_trigger in wyzai_code_claims guarantees a revisit never burns a
 * second slot. This function is idempotent: called twice for the same user
 * and trigger, it returns the same code and does not increment again.
 *
 * Call inside the caller's transaction.
 */

declare(strict_types=1);

/**
 * Returns ['code' => ..., 'coach_name' => ...] for the trigger, or null if
 * no active code is configured. Increments slots_issued only on first claim.
 *
 * @param string $trigger one of login|gate_1|gate_2|gate_3|package
 */
function wyzai_claim(PDO $pdo, int $userId, string $trigger): ?array
{
    // Already claimed? Return the code that was claimed, no new slot.
    $existing = $pdo->prepare(
        'SELECT c.code, c.coach_name
           FROM wyzai_code_claims cl
           JOIN wyzai_codes c ON c.id = cl.wyzai_code_id
          WHERE cl.user_id = ? AND cl.trigger_key = ? LIMIT 1'
    );
    $existing->execute([$userId, $trigger]);
    if ($row = $existing->fetch()) {
        return ['code' => $row['code'], 'coach_name' => $row['coach_name']];
    }

    // Pick the active code for this trigger.
    $find = $pdo->prepare(
        "SELECT id, code, coach_name
           FROM wyzai_codes
          WHERE trigger_key = ? AND status = 'active'
          ORDER BY id ASC LIMIT 1"
    );
    $find->execute([$trigger]);
    $code = $find->fetch();
    if (!$code) {
        return null;   // no code configured yet (placeholder era)
    }

    // Claim it. INSERT IGNORE protects against a concurrent double-claim.
    $claim = $pdo->prepare(
        'INSERT IGNORE INTO wyzai_code_claims (user_id, wyzai_code_id, trigger_key, claimed_at)
         VALUES (?, ?, ?, ?)'
    );
    $claim->execute([$userId, (int)$code['id'], $trigger, now_dt()]);

    if ($claim->rowCount() > 0) {
        // We inserted a fresh claim: increment the denormalised counter in step.
        $inc = $pdo->prepare('UPDATE wyzai_codes SET slots_issued = slots_issued + 1 WHERE id = ?');
        $inc->execute([(int)$code['id']]);
    }

    return ['code' => $code['code'], 'coach_name' => $code['coach_name']];
}
