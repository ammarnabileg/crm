<?php

declare(strict_types=1);

/**
 * AI engine tunables (docs/51 §12–13, AI Interview Engine P4). All behaviour is
 * config-driven; no provider keys live here (keys are per-tenant in
 * `tenant_ai_keys`, encrypted — the platform stores none).
 */
return [
    // PromptGuard (§13) — mandatory injection protection on every untrusted input.
    'guard' => [
        // Risk score (0–100) at/above which an input is rejected outright. Below it,
        // input is hardened (wrapped + sanitized) and the risk is recorded.
        'block_threshold' => 80,
        'max_input_chars' => 20000,
    ],

    // Token Optimizer (§13) — keep requests to context + summary + recent.
    'optimizer' => [
        'max_messages'    => 40,   // hard cap on history length sent upstream
        'chars_per_token' => 4,    // rough estimate for budgeting
    ],

    // Model Router (§12) — provider preference order when a tenant expresses none.
    'router' => [
        'preference' => ['anthropic', 'openai', 'gemini', 'deepseek', 'azure', 'sandbox'],
        // Weights for the routing score (higher = more important).
        'weights' => ['preference' => 0.5, 'cost' => 0.3, 'quality' => 0.2],
    ],
];
