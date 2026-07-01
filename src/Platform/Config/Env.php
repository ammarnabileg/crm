<?php

declare(strict_types=1);

namespace Nizam\Platform\Config;

use Nizam\Platform\Exception\ConfigException;

/**
 * Typed reader for process environment variables.
 *
 * Reads from `$_ENV`, `$_SERVER`, and {@see \getenv()} (in that order) and applies sane casts:
 * the strings "true"/"false"/"null"/"empty" become their PHP equivalents, and surrounding quotes
 * are stripped. Used at bootstrap to build configuration; application code should depend on
 * {@see Config} rather than reading env directly.
 */
final class Env
{
    /**
     * Non-instantiable static utility.
     */
    private function __construct()
    {
    }

    /**
     * Read an environment variable with casting, falling back to $default when unset.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $raw = self::raw($key);

        if ($raw === null) {
            return $default;
        }

        return self::cast($raw);
    }

    /**
     * Read a required environment variable as a string.
     *
     * @throws ConfigException When the variable is unset or an empty string.
     */
    public static function required(string $key): string
    {
        $raw = self::raw($key);

        if ($raw === null || $raw === '') {
            throw new ConfigException(sprintf('Required environment variable "%s" is not set.', $key));
        }

        return $raw;
    }

    /**
     * Read an environment variable coerced to bool.
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Read an environment variable coerced to int.
     */
    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);

        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Read an environment variable coerced to string.
     */
    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Fetch the raw, uncast string value from the available sources, or null when unset.
     */
    private static function raw(string $key): ?string
    {
        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return (string) $_SERVER[$key];
        }

        $value = getenv($key);

        return $value === false ? null : $value;
    }

    /**
     * Apply casting rules to a raw environment string.
     */
    private static function cast(string $value): mixed
    {
        $trimmed = trim($value);

        // Strip a single pair of matching surrounding quotes.
        if (strlen($trimmed) >= 2) {
            $first = $trimmed[0];
            $last = $trimmed[strlen($trimmed) - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($trimmed, 1, -1);
            }
        }

        return match (strtolower($trimmed)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}
