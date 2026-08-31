<?php
/**
 * AI provider credentials for server-side gate synthesis.  ABOVE public_html.
 *
 * STAGE E. Until 'enabled' is true, gate summaries still render complete
 * from the profile and tool results — the AI paragraph is simply omitted,
 * and ai_log records the call as 'skipped'. The product works without this.
 *
 * Hard ceiling: four AI calls per buyer, enforced by uq_ai_user_trigger in
 * the ai_log table, not by trusting this config.
 */

// GIT DEPLOY: real provider key goes in ai.local.php (gitignored).
$local = __DIR__ . '/ai.local.php';
if (is_file($local)) {
    return require $local;
}

return [
    'enabled'  => false,                     // keep false until Stage E
    'provider' => 'anthropic',               // PLACEHOLDER — provider name
    'api_key'  => 'REPLACE_AI_API_KEY',      // PLACEHOLDER — provider API key
    'model'    => 'REPLACE_AI_MODEL',        // PLACEHOLDER — model id
    'max_tokens' => 700,
    'timeout_seconds' => 20,                 // AI failure is logged, never fatal to gate close
];
