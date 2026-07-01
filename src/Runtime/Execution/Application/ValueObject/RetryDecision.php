<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application\ValueObject;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;

/**
 * The immutable verdict the {@see \Nizam\Runtime\Execution\Application\RetryEngine} returns for a failure.
 *
 * A decision says whether another attempt should proceed and, when it should, which attempt number it
 * is (1-based) and the jittered backoff delay in milliseconds that precedes it. When retry is refused
 * the attempt number reflects the attempts already made and the delay is zero. Being a value object,
 * it carries no behavior beyond exposing its verdict.
 */
final class RetryDecision implements ValueObject
{
    /**
     * @param bool $shouldRetry Whether another attempt should proceed.
     * @param int  $attempt     The attempt number (1-based): the next attempt when retrying, else attempts made.
     * @param int  $delayMs     The backoff delay before the next attempt in milliseconds (>= 0).
     */
    private function __construct(
        private readonly bool $shouldRetry,
        private readonly int $attempt,
        private readonly int $delayMs,
    ) {
        Assert::that($attempt >= 0, 'A retry decision attempt number must not be negative.');
        Assert::that($delayMs >= 0, 'A retry decision delay must not be negative.');
    }

    /**
     * A verdict to retry as the given attempt number after the given delay.
     *
     * @param int $attempt The 1-based number of the attempt to make next (>= 1).
     * @param int $delayMs The backoff delay before it, in milliseconds (>= 0).
     */
    public static function retry(int $attempt, int $delayMs): self
    {
        Assert::positive($attempt, 'A retry decision must name a positive next-attempt number.');

        return new self(true, $attempt, $delayMs);
    }

    /**
     * A verdict not to retry; the execution should fail.
     *
     * @param int $attemptsMade How many attempts had been made when the budget was exhausted.
     */
    public static function doNotRetry(int $attemptsMade): self
    {
        return new self(false, $attemptsMade, 0);
    }

    /**
     * Whether another attempt should proceed.
     */
    public function shouldRetry(): bool
    {
        return $this->shouldRetry;
    }

    /**
     * The attempt number: the next attempt when retrying, otherwise the attempts already made.
     */
    public function attempt(): int
    {
        return $this->attempt;
    }

    /**
     * The backoff delay before the next attempt, in milliseconds.
     */
    public function delayMs(): int
    {
        return $this->delayMs;
    }

    /**
     * Structural equality across every attribute.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->shouldRetry === $this->shouldRetry
            && $other->attempt === $this->attempt
            && $other->delayMs === $this->delayMs;
    }
}
