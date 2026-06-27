<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Services\AI\AiPrompt;
use App\Services\AI\AiResult;

/**
 * Google Gemini generateContent adapter (docs/51 §12). Gemini passes the API key as
 * a query parameter, uses `contents` with `parts`, maps the assistant role to
 * `model`, and carries system text in `systemInstruction`.
 */
final class GeminiProvider extends HttpAiProvider
{
    public function key(): string
    {
        return 'gemini';
    }

    protected function defaultModel(): string
    {
        return 'gemini-1.5-flash';
    }

    protected function buildRequest(string $apiKey, string $model, AiPrompt $prompt, array $credentials): array
    {
        $system = '';
        $contents = [];
        foreach ($prompt->messages as $m) {
            $role = (string) ($m['role'] ?? 'user');
            $content = (string) ($m['content'] ?? '');
            if ($role === 'system') {
                $system .= ($system === '' ? '' : "\n") . $content;
                continue;
            }
            $contents[] = ['role' => $role === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $content]]];
        }
        if ($contents === []) {
            $contents[] = ['role' => 'user', 'parts' => [['text' => $prompt->text()]]];
        }

        $payload = ['contents' => $contents];
        if ($system !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }
        $gen = [];
        if (($t = $this->temperature($prompt)) !== null) {
            $gen['temperature'] = $t;
        }
        if (($mt = $this->maxTokens($prompt)) !== null) {
            $gen['maxOutputTokens'] = $mt;
        }
        if ($gen !== []) {
            $payload['generationConfig'] = $gen;
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);

        return [$url, ['Content-Type: application/json'], (string) json_encode($payload, self::JSON)];
    }

    protected function parse(array $json, string $model): AiResult
    {
        $text = (string) ($json['candidates'][0]['content']['parts'][0]['text'] ?? '');
        if ($text === '') {
            return AiResult::failure('empty_response', $this->key(), $model);
        }
        $usage = is_array($json['usageMetadata'] ?? null) ? $json['usageMetadata'] : [];

        return AiResult::success(
            $text,
            $this->key(),
            $model,
            (int) ($usage['promptTokenCount'] ?? 0),
            (int) ($usage['candidatesTokenCount'] ?? 0),
            $json
        );
    }
}
