<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine\Infrastructure\Providers;

use HaHireAI\Modules\AiEngine\Contracts\AiProvider;
use HaHireAI\Modules\AiEngine\Domain\AiResponse;

/**
 * A built-in, network-free provider. It produces a deterministic, useful
 * response from the rendered prompt so the platform works out-of-the-box (and
 * tests run offline). Real providers (OpenAI/Anthropic) implement the same
 * contract and are selected per workspace.
 */
final class EchoProvider implements AiProvider
{
    public function key(): string
    {
        return 'echo';
    }

    public function complete(string $prompt, array $options = []): AiResponse
    {
        $inputTokens = $this->countTokens($prompt);
        $text = "[AI · echo] " . $this->summarize($prompt);

        return new AiResponse(
            text: $text,
            inputTokens: $inputTokens,
            outputTokens: $this->countTokens($text),
            model: $options['model'] ?? 'echo-1',
        );
    }

    private function summarize(string $prompt): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $prompt) ?? '');
        $words = array_slice(explode(' ', $clean), -60);

        return 'Summary based on the provided context: ' . implode(' ', $words);
    }

    private function countTokens(string $text): int
    {
        return (int) max(1, ceil(strlen($text) / 4));
    }
}
