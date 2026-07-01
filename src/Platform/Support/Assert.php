<?php

declare(strict_types=1);

namespace Nizam\Platform\Support;

use Nizam\Platform\Exception\InvalidArgumentException;

/**
 * Lightweight guard helpers that enforce method preconditions.
 *
 * Each assertion throws {@see InvalidArgumentException} (an SPL-compatible type) when its
 * condition is not met and otherwise returns normally. Guards are for *programmer* errors —
 * unmet invariants that indicate a bug — not for validating untrusted user input, which belongs
 * in the validation layer and is reported via {@see \Nizam\Platform\Support\Result}.
 */
final class Assert
{
    /**
     * Non-instantiable static utility.
     */
    private function __construct()
    {
    }

    /**
     * Assert that a condition holds.
     *
     * @throws InvalidArgumentException When $condition is false.
     */
    public static function that(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * Assert that a value is not null and return it narrowed.
     *
     * @template T
     *
     * @param T|null $value
     *
     * @return T
     *
     * @throws InvalidArgumentException When $value is null.
     */
    public static function notNull(mixed $value, string $message = 'Value must not be null.'): mixed
    {
        if ($value === null) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    /**
     * Assert that a string is not empty after trimming whitespace.
     *
     * @throws InvalidArgumentException When the string is empty or whitespace-only.
     */
    public static function notEmpty(string $value, string $message = 'Value must not be empty.'): string
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    /**
     * Assert that an integer is strictly greater than zero.
     *
     * @throws InvalidArgumentException When $value is not positive.
     */
    public static function positive(int $value, string $message = 'Value must be a positive integer.'): int
    {
        if ($value <= 0) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    /**
     * Assert that a value matches a regular expression.
     *
     * @throws InvalidArgumentException When the value does not match the pattern.
     */
    public static function matches(string $value, string $pattern, string $message = 'Value has an invalid format.'): string
    {
        if (preg_match($pattern, $value) !== 1) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    /**
     * Assert that a value is one of an allowed set.
     *
     * @param array<int, mixed> $allowed
     *
     * @throws InvalidArgumentException When $value is not contained in $allowed.
     */
    public static function oneOf(mixed $value, array $allowed, string $message = 'Value is not an allowed option.'): mixed
    {
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }
}
