<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Services\AI\AiPrompt;
use App\Services\AI\AiResult;

/**
 * Anthropic Claude Messages adapter (docs/51 §12). Claude takes system text as a
 * top-level `system` field (not a message role) and requires `max_tokens`, so the
 * adapter splits system messages out and defaults max_tokens when the caller omits it.
 */
final class ClaudeProvider extends HttpAiProvider
{
    public function key(): string
    {
        return 'anthropic';
    }

    protected function defaultModel(): string
    {
        return 'claude-3-5-sonnet-latest';
    }

    protected function buildRequest(string $apiKey, string $model, AiPrompt $prompt, array $credentials): array
    {
        $system = '';
        $messages = [];
        foreach ($prompt->messages as $m) {
            $role = (string) ($m['role'] ?? 'user');
            $content = (string) ($m['content'] ?? '');
            if ($role === 'system') {
                $system .= ($system === '' ? '' : "\n") . $content;
                continue;
            }
            $messages[] = ['role' => $role === 'assistant' ? 'assistant' : 'user', 'content' => $content];
        }
        if ($messages === []) {
            $messages[] = ['role' => 'user', 'content' => $prompt->text()];
        }

        $payload = [
            'model'      => $model,
            'max_tokens' => $this->maxTokens($prompt) ?? 1024,
            'messages'   => $messages,
        ];
        if ($system !== '') {
            $payload['system'] = $system;
        }
        if (($t = $this->temperature($prompt)) !== null) {
            $payload['temperature'] = $t;
        }

        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ];

        return ['https://api.anthropic.com/v1/messages', $headers, (string) json_encode($payload, self::JSON)];
    }

    protected function parse(array $json, string $model): AiResult
    {
        $text = (string) ($json['content'][0]['text'] ?? '');
        if ($text === '') {
            return AiResult::failure('empty_response', $this->key(), $model);
        }
        $usage = is_array($json['usage'] ?? null) ? $json['usage'] : [];

        return AiResult::success(
            $text,
            $this->key(),
            (string) ($json['model'] ?? $model),
            (int) ($usage['input_tokens'] ?? 0),
            (int) ($usage['output_tokens'] ?? 0),
            $json
        );
    }
}
