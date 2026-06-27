<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Contracts\AI\AiProvider;
use App\Services\AI\AiPrompt;
use App\Services\AI\AiResult;

/**
 * A deterministic, offline provider (docs/51 §19 Sandbox/Simulation). It performs
 * no network call, so it powers tests and a real sandbox/simulation mode where a
 * workspace can dry-run a workflow or blueprint with zero cost and reproducible
 * output. Construct with $fail=true to simulate an upstream failure and exercise
 * the AiGateway's fallback chain.
 */
final class FakeProvider implements AiProvider
{
    public function __construct(
        private readonly string $key = 'sandbox',
        private readonly bool $fail = false,
        private readonly string $cannedText = 'OK',
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function supports(string $capability): bool
    {
        return true;
    }

    public function complete(AiPrompt $prompt, array $credentials, array $options = []): AiResult
    {
        if ($this->fail) {
            return AiResult::failure("simulated failure ({$this->key})", $this->key);
        }

        $model = (string) ($options['model'] ?? $prompt->options['model'] ?? 'sandbox-1');
        $inputTokens = (int) ceil(mb_strlen($prompt->text()) / 4);
        $text = $this->cannedText;

        return AiResult::success(
            $text,
            $this->key,
            $model,
            $inputTokens,
            (int) ceil(mb_strlen($text) / 4),
            ['sandbox' => true]
        );
    }
}
