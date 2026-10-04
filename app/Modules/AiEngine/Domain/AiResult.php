<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine\Domain;

/** The engine's result for a capability run (advisory — humans decide). */
final class AiResult
{
    public function __construct(
        public readonly string $text,
        public readonly string $provider,
        public readonly ?string $model,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $costCents,
        public readonly ?string $fallbackFrom = null,
    ) {
    }
}
