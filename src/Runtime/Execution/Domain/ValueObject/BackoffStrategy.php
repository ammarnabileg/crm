<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\ValueObject;

/**
 * How a {@see RetryPolicy} spaces successive retry attempts.
 *
 * String-backed for stable persistence in the executions table and read models. {@see self::Fixed}
 * keeps the same delay between every attempt; {@see self::Exponential} doubles the delay with each
 * successive attempt.
 */
enum BackoffStrategy: string
{
    /** The same base delay is used before every retry. */
    case Fixed = 'fixed';

    /** The delay doubles with each successive attempt (base * 2^(attempt-1)). */
    case Exponential = 'exponential';
}
