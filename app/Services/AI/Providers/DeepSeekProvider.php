<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

/**
 * DeepSeek adapter (docs/51 §12). DeepSeek exposes an OpenAI-compatible Chat
 * Completions API, so it reuses OpenAiProvider's request/response handling and only
 * overrides the endpoint, key and default model — no duplicated logic.
 */
final class DeepSeekProvider extends OpenAiProvider
{
    public function key(): string
    {
        return 'deepseek';
    }

    protected function defaultModel(): string
    {
        return 'deepseek-chat';
    }

    protected function endpoint(string $model, array $credentials): string
    {
        return 'https://api.deepseek.com/chat/completions';
    }
}
