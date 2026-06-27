<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Services\AI\AiPrompt;
use App\Services\AI\AiResult;

/**
 * OpenAI Chat Completions adapter (docs/51 §12). Also the base for the other
 * OpenAI-compatible providers (DeepSeek, Azure OpenAI), which only differ in
 * endpoint/auth — so the request/response shape is written once here.
 */
class OpenAiProvider extends HttpAiProvider
{
    public function key(): string
    {
        return 'openai';
    }

    protected function defaultModel(): string
    {
        return 'gpt-4o-mini';
    }

    /** @param array<string,mixed> $credentials */
    protected function endpoint(string $model, array $credentials): string
    {
        return 'https://api.openai.com/v1/chat/completions';
    }

    /** @param array<string,mixed> $credentials @return array<int,string> */
    protected function authHeaders(string $apiKey, array $credentials): array
    {
        return ['Authorization: Bearer ' . $apiKey];
    }

    protected function buildRequest(string $apiKey, string $model, AiPrompt $prompt, array $credentials): array
    {
        $payload = ['model' => $model, 'messages' => $this->plainMessages($prompt)];
        if (($t = $this->temperature($prompt)) !== null) {
            $payload['temperature'] = $t;
        }
        if (($mt = $this->maxTokens($prompt)) !== null) {
            $payload['max_tokens'] = $mt;
        }

        $headers = array_merge(['Content-Type: application/json'], $this->authHeaders($apiKey, $credentials));

        return [$this->endpoint($model, $credentials), $headers, (string) json_encode($payload, self::JSON)];
    }

    protected function parse(array $json, string $model): AiResult
    {
        $text = (string) ($json['choices'][0]['message']['content'] ?? '');
        if ($text === '') {
            return AiResult::failure('empty_response', $this->key(), $model);
        }
        $usage = is_array($json['usage'] ?? null) ? $json['usage'] : [];

        return AiResult::success(
            $text,
            $this->key(),
            (string) ($json['model'] ?? $model),
            (int) ($usage['prompt_tokens'] ?? 0),
            (int) ($usage['completion_tokens'] ?? 0),
            $json
        );
    }
}
