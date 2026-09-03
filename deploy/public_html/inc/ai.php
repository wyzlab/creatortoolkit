<?php
/**
 * ai.php — Stage E. One short, personal coach note per gate, written from what
 * the learner actually built. Called AFTER the gate-close transaction commits,
 * so a slow or failing AI call never holds a database lock and never blocks the
 * gate from closing.
 *
 * Hard ceiling: four AI calls per buyer (gate_1, gate_2, gate_3, package). The
 * real guarantee is the uq_ai_user_trigger unique key on ai_log — at most one
 * row per (user, trigger), so at most four rows, so at most four calls ever.
 * This file also short-circuits before calling, so the network call is made
 * once per gate in normal operation.
 *
 * Failure is always logged and never thrown: the summary still renders and the
 * email still sends, just without the extra paragraph.
 */

declare(strict_types=1);

/** The AI config (config/ai.php, overridable by ai.local.php). */
function ai_config(): array
{
    static $cfg = null;
    if ($cfg === null) { $cfg = require CONFIG_DIR . '/ai.php'; }
    return $cfg;
}

/** trigger_key for a gate number (0 = the full package). */
function ai_trigger_key(int $gate): string
{
    return $gate === 0 ? 'package' : 'gate_' . $gate;
}

/**
 * Generate (or return the already-stored) coach note for a closed gate.
 * Returns the paragraph text, or null when there is none (disabled, failed, or
 * the gate is not closed yet). Safe to call more than once — it will not make a
 * second API call for the same gate.
 */
function ai_generate_for_gate(PDO $pdo, int $uid, int $gate): ?string
{
    $trigger = ai_trigger_key($gate);

    // The gate must be closed (its result row must exist).
    $gr = $pdo->prepare('SELECT summary_json, ai_paragraph FROM gate_results
                          WHERE user_id = ? AND gate_number = ? LIMIT 1');
    $gr->execute([$uid, $gate]);
    $row = $gr->fetch();
    if (!$row) { return null; }

    // Already written? Return it and make no call.
    if ($row['ai_paragraph'] !== null && trim((string)$row['ai_paragraph']) !== '') {
        return (string)$row['ai_paragraph'];
    }

    // Already attempted (ok/failed/skipped)? Respect the ceiling — do not retry.
    $seen = $pdo->prepare('SELECT status FROM ai_log WHERE user_id = ? AND trigger_key = ? LIMIT 1');
    $seen->execute([$uid, $trigger]);
    if ($seen->fetch()) { return null; }

    $cfg = ai_config();

    // Disabled: record one 'skipped' row (counts against nothing; keeps the log honest).
    if (empty($cfg['enabled'])) {
        ai_log_write($pdo, $uid, $trigger, 'skipped', null, null, null, null);
        return null;
    }

    // Build the prompt from the structured summary plus a little profile context.
    $summary = json_decode((string)($row['summary_json'] ?? '{}'), true) ?: [];
    $pf = $pdo->prepare('SELECT profile_json FROM user_profile WHERE user_id = ? LIMIT 1');
    $pf->execute([$uid]);
    $profile = json_decode((string)$pf->fetchColumn() ?: '{}', true) ?: [];
    unset($profile['_stale']);
    [$system, $userMsg] = ai_build_prompt($gate, $summary, $profile);

    try {
        $res  = ai_call($cfg, $system, $userMsg);
        $text = trim((string)($res['text'] ?? ''));
        if ($text === '') { throw new \RuntimeException('empty completion'); }

        $pdo->prepare('UPDATE gate_results SET ai_paragraph = ? WHERE user_id = ? AND gate_number = ?')
            ->execute([$text, $uid, $gate]);
        ai_log_write($pdo, $uid, $trigger, 'ok', $res['model'] ?? null,
                     $res['tokens_in'] ?? null, $res['tokens_out'] ?? null, null);
        return $text;
    } catch (\Throwable $e) {
        error_log('ai_generate_for_gate failed (' . $trigger . '): ' . $e->getMessage());
        ai_log_write($pdo, $uid, $trigger, 'failed', $cfg['model'] ?? null, null, null,
                     substr($e->getMessage(), 0, 500));
        return null;
    }
}

/** Insert one ai_log row, ceiling-safe (INSERT IGNORE on the unique key). */
function ai_log_write(PDO $pdo, int $uid, string $trigger, string $status,
                      ?string $model, ?int $tin, ?int $tout, ?string $error): void
{
    try {
        $pdo->prepare('INSERT IGNORE INTO ai_log
                        (user_id, trigger_key, model, tokens_in, tokens_out, status, error, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$uid, $trigger, $model, $tin, $tout, $status, $error, now_dt()]);
    } catch (\Throwable $e) {
        error_log('ai_log_write failed: ' . $e->getMessage());
    }
}

/**
 * Build [system, user] messages for a gate's coach note. The facts come from
 * the gate summary the learner already saw, so the note can never contradict
 * what is on screen.
 */
function ai_build_prompt(int $gate, array $summary, array $profile): array
{
    $labels = [
        1 => 'Gate 1 — Get Clear (they named their ideal client, their niche, and validated their idea)',
        2 => 'Gate 2 — Build Your Offer (they shaped an offer and sparked a course from their content)',
        3 => 'Gate 3 — Price, Launch, Sell (they set a price, mapped a launch, and prepared a discovery call)',
        0 => 'The full toolkit is complete (client, course, price, and a plan to sell)',
    ];
    $stage = $labels[$gate] ?? ('Gate ' . $gate);

    // A compact, human-readable set of facts (no raw JSON dumped at the model).
    $facts = [];
    $push = function (string $k, $v) use (&$facts) {
        $v = is_array($v) ? '' : trim((string)$v);
        if ($v !== '') { $facts[] = $k . ': ' . $v; }
    };
    $push('Ideal client', $summary['learner'] ?? ($profile['avatar']['name'] ?? ''));
    $push('Niche', $summary['niche'] ?? ($profile['clarity']['niche'] ?? ''));
    $push('Validation verdict', $summary['verdict'] ?? ($profile['validation']['verdict'] ?? ''));
    $push('Offer', $summary['offer_who'] ?? ($profile['offer']['who'] ?? ''));
    $push('Course theme', $summary['theme'] ?? ($profile['sparker']['theme'] ?? ''));
    $push('Price (PHP)', $summary['price'] ?? ($profile['pricing']['final_price'] ?? ''));
    $factBlock = $facts ? implode("\n", $facts) : '(no specific details captured)';

    $system =
        "You are a warm, plain-spoken coach for WyzCore Academy, writing to a Filipino coach, "
        . "consultant, or creator who just finished a stage of the DIY Creator Starter Toolkit. "
        . "Write ONE short paragraph (2 to 3 sentences, about 45 words). Speak directly to them as "
        . "\"you\". Grade-8 English, encouraging and specific — mention what they actually built. "
        . "No headings, no lists, no markdown, no emojis, no quotation marks around the whole reply. "
        . "Do not invent facts beyond what you are given. End by pointing them at their very next step.";

    $userMsg =
        "Stage just completed: " . $stage . "\n\n"
        . "What they built:\n" . $factBlock . "\n\n"
        . "Write their coach note now.";

    return [$system, $userMsg];
}

/**
 * Make the provider call. Anthropic Messages API by default. Returns
 * ['text','model','tokens_in','tokens_out']. Throws on any transport or API
 * error so the caller can log a 'failed' row.
 */
function ai_call(array $cfg, string $system, string $userMsg): array
{
    $provider = $cfg['provider'] ?? 'anthropic';
    if ($provider !== 'anthropic') {
        throw new \RuntimeException('Unsupported AI provider: ' . $provider);
    }
    $key = (string)($cfg['api_key'] ?? '');
    $model = (string)($cfg['model'] ?? '');
    if ($key === '' || strpos($key, 'REPLACE_') === 0) {
        throw new \RuntimeException('AI api_key not configured');
    }
    if ($model === '' || strpos($model, 'REPLACE_') === 0) {
        throw new \RuntimeException('AI model not configured');
    }

    $payload = json_encode([
        'model'      => $model,
        'max_tokens' => (int)($cfg['max_tokens'] ?? 700),
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $userMsg]],
    ], JSON_UNESCAPED_UNICODE);

    // Endpoint is overridable (a gateway, or a test mock); defaults to Anthropic.
    $endpoint = $cfg['endpoint'] ?? 'https://api.anthropic.com/v1/messages';
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => (int)($cfg['timeout_seconds'] ?? 20),
        CURLOPT_HTTPHEADER     => [
            'content-type: application/json',
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
        ],
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new \RuntimeException('HTTP error: ' . $err);
    }
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode((string)$body, true);
    if ($code < 200 || $code >= 300 || !is_array($data)) {
        $msg = is_array($data) ? ($data['error']['message'] ?? '') : '';
        throw new \RuntimeException('API ' . $code . ($msg !== '' ? ': ' . $msg : ''));
    }

    // Concatenate any text blocks in the response.
    $text = '';
    foreach (($data['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') { $text .= $block['text']; }
    }

    return [
        'text'       => $text,
        'model'      => $data['model'] ?? $model,
        'tokens_in'  => $data['usage']['input_tokens'] ?? null,
        'tokens_out' => $data['usage']['output_tokens'] ?? null,
    ];
}
