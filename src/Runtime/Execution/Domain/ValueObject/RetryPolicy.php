<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\ValueObject;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;

/**
 * The immutable rule set governing how many times an execution may retry and how retries are spaced.
 *
 * A retry policy caps the total number of attempts (the initial attempt plus retries), fixes the
 * base delay between attempts, chooses a {@see BackoffStrategy}, and toggles jitter. It is a pure
 * value object: {@see self::shouldRetry()} answers whether another attempt is permitted given how
 * many have already been made, and {@see self::nextDelayMs()} computes the deterministic backoff for
 * a given attempt number. Jitter is expressed as a flag only; the actual randomization is applied by
 * the application-layer retry engine so the domain stays deterministic and testable.
 */
final class RetryPolicy implements ValueObject
{
    /**
     * @param int             $maxAttempts The maximum number of attempts allowed (>= 1).
     * @param int             $baseDelayMs The base delay between attempts in milliseconds (>= 0).
     * @param BackoffStrategy $backoff     The spacing strategy for successive attempts.
     * @param bool            $jitter      Whether the retry engine should randomize the computed delay.
     */
    public function __construct(
        private readonly int $maxAttempts,
        private readonly int $baseDelayMs,
        private readonly BackoffStrategy $backoff,
        private readonly bool $jitter,
    ) {
        Assert::positive($maxAttempts, 'A retry policy must allow at least one attempt.');
        Assert::that($baseDelayMs >= 0, 'The base retry delay must not be negative.');
    }

    /**
     * A sensible default policy: three attempts, 100 ms base delay, exponential backoff, no jitter.
     */
    public static function default(): self
    {
        return new self(3, 100, BackoffStrategy::Exponential, false);
    }

    /**
     * A policy that never retries: a single attempt only.
     */
    public static function none(): self
    {
        return new self(1, 0, BackoffStrategy::Fixed, false);
    }

    /**
     * Whether another attempt is permitted given the number already made.
     *
     * @param int $attemptsMade The count of attempts already performed (>= 0).
     */
    public function shouldRetry(int $attemptsMade): bool
    {
        Assert::that($attemptsMade >= 0, 'attemptsMade must not be negative.');

        return $attemptsMade < $this->maxAttempts;
    }

    /**
     * The deterministic backoff delay, in milliseconds, before the given attempt number.
     *
     * Attempt numbers are 1-based: attempt 1 is the initial try and always incurs no delay; attempt 2
     * incurs the first backoff. For {@see BackoffStrategy::Fixed} every backoff equals the base delay;
     * for {@see BackoffStrategy::Exponential} the delay doubles each time (base * 2^(attempt-2)). The
     * returned value excludes jitter, which the retry engine layers on when {@see self::hasJitter()}.
     *
     * @param int $attempt The 1-based attempt number the delay precedes (>= 1).
     */
    public function nextDelayMs(int $attempt): int
    {
        Assert::positive($attempt, 'The attempt number must be a positive integer.');

        if ($attempt <= 1) {
            return 0;
        }

        return match ($this->backoff) {
            BackoffStrategy::Fixed => $this->baseDelayMs,
            BackoffStrategy::Exponential => $this->baseDelayMs * (2 ** ($attempt - 2)),
        };
    }

    /**
     * The maximum number of attempts allowed.
     */
    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * The base delay between attempts, in milliseconds.
     */
    public function baseDelayMs(): int
    {
        return $this->baseDelayMs;
    }

    /**
     * The spacing strategy for successive attempts.
     */
    public function backoff(): BackoffStrategy
    {
        return $this->backoff;
    }

    /**
     * Whether the retry engine should randomize the computed delay.
     */
    public function hasJitter(): bool
    {
        return $this->jitter;
    }

    /**
     * Structural equality across every attribute.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->maxAttempts === $this->maxAttempts
            && $other->baseDelayMs === $this->baseDelayMs
            && $other->backoff === $this->backoff
            && $other->jitter === $this->jitter;
    }

    /**
     * A scalar-only representation suitable for JSON persistence and read models.
     *
     * @return array{maxAttempts: int, baseDelayMs: int, backoff: string, jitter: bool}
     */
    public function toArray(): array
    {
        return [
            'maxAttempts' => $this->maxAttempts,
            'baseDelayMs' => $this->baseDelayMs,
            'backoff' => $this->backoff->value,
            'jitter' => $this->jitter,
        ];
    }
}
