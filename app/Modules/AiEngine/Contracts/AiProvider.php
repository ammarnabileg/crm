<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine\Contracts;

use HaHireAI\Modules\AiEngine\Domain\AiResponse;

/**
 * A pluggable AI provider. New providers (OpenAI, Anthropic, Gemini, …) are
 * added by implementing this contract and registering them — modules NEVER call
 * a provider directly; they request capabilities from the AI Engine
 * (docs/AI_ENGINE.md, docs/PROVIDER_LAYER.md).
 */
interface AiProvider
{
    /** Stable provider key, e.g. "openai", "anthropic", "echo". */
    public function key(): string;

    /**
     * Run a completion. MUST throw on failure so the engine can fall back.
     *
     * @param  array{model?: string, api_key?: ?string, temperature?: float, max_tokens?: int}  $options
     */
    public function complete(string $prompt, array $options = []): AiResponse;
}
