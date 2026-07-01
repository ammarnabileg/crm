<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application;

use Nizam\Runtime\Execution\Application\ValueObject\RetryDecision;
use Nizam\Runtime\Execution\Domain\ValueObject\RetryPolicy;

/**
 * The application service that decides, on a failure, whether to retry an execution and how long to wait.
 *
 * The {@see RetryPolicy} is a pure domain value object: it answers whether another attempt is
 * permitted and computes the deterministic backoff for a given attempt. This engine layers the
 * application concerns on top — turning the yes/no plus delay into a {@see RetryDecision}, and
 * applying jitter (when the policy requests it) using an injected, seedable randomizer so retries
 * stay testable and reproducible. The domain never randomizes; that lives here so the aggregate
 * remains deterministic.
 *
 * @phpstan-type Randomizer callable(int, int): int
 */
final class RetryEngine
{
    /** @var callable(int, int): int */
    private $randomizer;

    /**
     * @param (callable(int, int): int)|null $randomizer A bounded integer source `fn(min,max): int`;
     *                                                    defaults to {@see random_int}. Inject a fake for tests.
     */
    public function __construct(?callable $randomizer = null)
    {
        $this->randomizer = $randomizer ?? static fn (int $min, int $max): int => random_int($min, $max);
    }

    /**
     * Decide whether the next attempt should proceed and, if so, after what delay.
     *
     * The attempt counter is the number of attempts already made (1-based, as the aggregate tracks it).
     * When the policy permits another attempt, the returned decision carries the jittered backoff that
     * precedes attempt {@code $attemptsMade + 1}; otherwise it carries a "do not retry" verdict and no
     * delay. Jitter, when enabled, randomizes the delay within `[0, computedDelay]` using the injected
     * randomizer, so it never exceeds the policy's computed backoff.
     *
     * @param RetryPolicy $policy       The retry rules to apply.
     * @param int         $attemptsMade How many attempts have already been made (>= 0).
     */
    public function decide(RetryPolicy $policy, int $attemptsMade): RetryDecision
    {
        if (!$policy->shouldRetry($attemptsMade)) {
            return RetryDecision::doNotRetry($attemptsMade);
        }

        $nextAttempt = $attemptsMade + 1;
        $baseDelay = $policy->nextDelayMs($nextAttempt);
        $delay = $policy->hasJitter() ? $this->applyJitter($baseDelay) : $baseDelay;

        return RetryDecision::retry($nextAttempt, $delay);
    }

    /**
     * Randomize a delay within the inclusive range `[0, delayMs]`, leaving a zero delay untouched.
     *
     * @param int $delayMs The computed, un-jittered backoff (>= 0).
     */
    private function applyJitter(int $delayMs): int
    {
        if ($delayMs <= 0) {
            return 0;
        }

        return ($this->randomizer)(0, $delayMs);
    }
}
