<?php

declare(strict_types=1);

namespace Nizam\Platform\Support;

use Nizam\Platform\Exception\PlatformException;

/**
 * A typed result of an operation that can succeed or fail without throwing.
 *
 * `Result` is the platform's canonical way to model *expected* failures (validation,
 * business-rule violations, not-found) as return values rather than exceptions. Exceptions
 * remain reserved for *unexpected* / programmer errors. This keeps control flow explicit and
 * makes application-layer code total and testable.
 *
 * The value is intentionally `mixed`; callers document the concrete success type in their own
 * PHPDoc (e.g. `@return Result<AgentRun>`), which static analysis understands.
 *
 * @psalm-immutable
 */
final class Result
{
    /**
     * @param bool                 $ok           Whether the operation succeeded.
     * @param mixed                $value        The success value (null when failed).
     * @param string|null          $errorCode    Machine-readable error code (e.g. "IDENTITY.INVALID_CREDENTIALS").
     * @param string|null          $errorMessage Human/business-language message (never a stack trace).
     * @param array<string, mixed> $context      Structured, non-sensitive error context.
     */
    private function __construct(
        private readonly bool $ok,
        private readonly mixed $value,
        private readonly ?string $errorCode,
        private readonly ?string $errorMessage,
        private readonly array $context,
    ) {
    }

    /**
     * Create a successful result carrying an optional value.
     */
    public static function ok(mixed $value = null): self
    {
        return new self(true, $value, null, null, []);
    }

    /**
     * Create a failed result with a machine code, business message and structured context.
     *
     * @param array<string, mixed> $context
     */
    public static function err(string $code, string $message = '', array $context = []): self
    {
        return new self(false, null, $code, $message === '' ? $code : $message, $context);
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function isErr(): bool
    {
        return !$this->ok;
    }

    /**
     * The success value.
     *
     * @throws PlatformException When called on a failed result (programmer error).
     */
    public function value(): mixed
    {
        if (!$this->ok) {
            throw new PlatformException(sprintf(
                'Cannot read value() of a failed Result (%s: %s).',
                (string) $this->errorCode,
                (string) $this->errorMessage,
            ));
        }

        return $this->value;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * Transform the success value, leaving failures untouched.
     *
     * @param callable(mixed): mixed $fn
     */
    public function map(callable $fn): self
    {
        if (!$this->ok) {
            return $this;
        }

        return self::ok($fn($this->value));
    }

    /**
     * Transform the error into another error, leaving successes untouched.
     *
     * @param callable(string, string, array<string, mixed>): self $fn
     */
    public function mapErr(callable $fn): self
    {
        if ($this->ok) {
            return $this;
        }

        return $fn((string) $this->errorCode, (string) $this->errorMessage, $this->context);
    }

    /**
     * Return the success value or a fallback when failed.
     */
    public function unwrapOr(mixed $default): mixed
    {
        return $this->ok ? $this->value : $default;
    }
}
