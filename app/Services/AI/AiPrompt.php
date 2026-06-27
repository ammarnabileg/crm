<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * An immutable prompt passed to an Ai provider (docs/51 §12). Messages are
 * role-tagged ([{role: system|user|assistant, content, trusted?}]). The `trusted`
 * flag marks platform-authored messages; messages WITHOUT it (candidate answers,
 * external text) are untrusted and are always hardened by the PromptGuard before
 * sending.
 */
final class AiPrompt
{
    /**
     * @param array<int, array{role:string, content:string, trusted?:bool}> $messages
     * @param array<string,mixed> $options model / max_tokens / temperature / etc.
     */
    public function __construct(
        public readonly array $messages,
        public readonly string $capability = 'chat',
        public readonly array $options = [],
    ) {
    }

    /** @param array<int, array<string,mixed>> $messages */
    public function withMessages(array $messages): self
    {
        return new self($messages, $this->capability, $this->options);
    }

    /** @param array<string,mixed> $options */
    public function withOptions(array $options): self
    {
        return new self($this->messages, $this->capability, array_merge($this->options, $options));
    }

    /** Flattened text of all messages (for hashing / token estimates). */
    public function text(): string
    {
        return implode("\n", array_map(
            static fn (array $m): string => (string) ($m['content'] ?? ''),
            $this->messages
        ));
    }
}
