<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine\Domain;

/** The result of one provider completion. */
final class AiResponse
{
    public function __construct(
        public readonly string $text,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly ?string $model = null,
    ) {
    }
}
