<?php

declare(strict_types=1);

namespace App\Contracts\AI;

use App\Services\AI\AiPrompt;
use App\Services\AI\AiResult;

/**
 * A swappable AI provider (docs/51 §12). OpenAI / Claude / Gemini / DeepSeek /
 * Azure / a sandbox fake are implementations selected per call by the ModelRouter
 * and invoked by the AiGateway (which applies the PromptGuard + TokenOptimizer and
 * the fallback chain first). Implementations use ONLY the tenant's own credentials
 * — the platform stores no keys.
 */
interface AiProvider
{
    /** Stable provider key, e.g. 'anthropic', 'openai', 'sandbox'. */
    public function key(): string;

    /** Whether this provider can serve a capability ('chat', 'analysis', …). */
    public function supports(string $capability): bool;

    /**
     * Complete a prompt. Implementations must NEVER throw for an upstream failure —
     * return AiResult::failure() so the AiGateway can fall back to the next provider.
     *
     * @param array<string,mixed> $credentials the tenant's decrypted secrets
     */
    public function complete(AiPrompt $prompt, array $credentials, array $options = []): AiResult;
}
