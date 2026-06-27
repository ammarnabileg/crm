<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * An immutable result from an AI provider (docs/51 §12). `ok=false` carries the
 * error so the AiGateway can fall back without exceptions. Token counts feed the
 * cost/observability layer (§17).
 */
final class AiResult
{
    /** @param array<string,mixed> $raw */
    public function __construct(
        public readonly bool $ok,
        public readonly string $text = '',
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly string $model = '',
        public readonly string $provider = '',
        public readonly ?string $error = null,
        public readonly array $raw = [],
    ) {
    }

    /** @param array<string,mixed> $raw */
    public static function success(
        string $text,
        string $provider,
        string $model,
        int $inputTokens = 0,
        int $outputTokens = 0,
        array $raw = []
    ): self {
        return new self(true, $text, $inputTokens, $outputTokens, $model, $provider, null, $raw);
    }

    public static function failure(string $error, string $provider = '', string $model = ''): self
    {
        return new self(false, '', 0, 0, $model, $provider, $error, []);
    }
}
