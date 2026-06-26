<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env parser.
 *
 * Reads a dotenv-style file into the process environment. Kept deliberately
 * small (no external dotenv library) because the platform must run with zero
 * Composer dependencies. Supports quoted values, comments, and `export`
 * prefixes.
 */
final class Env
{
    private static array $loaded = [];

    public static function load(string $path): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            if (! str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = self::normalizeValue(trim($value));

            self::$loaded[$name] = $value;
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv($name . '=' . $value);
        }
    }

    /**
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$loaded)) {
            return self::cast(self::$loaded[$key]);
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return self::cast($value);
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$loaded)
            || array_key_exists($key, $_ENV)
            || getenv($key) !== false;
    }

    private static function normalizeValue(string $value): string
    {
        // Strip a single trailing inline comment when the value is unquoted.
        if ($value !== '' && $value[0] !== '"' && $value[0] !== "'") {
            $hashPos = strpos($value, ' #');
            if ($hashPos !== false) {
                $value = rtrim(substr($value, 0, $hashPos));
            }
        }

        $length = strlen($value);
        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
                if ($first === '"') {
                    $value = str_replace(['\\n', '\\r', '\\"', '\\\\'], ["\n", "\r", '"', '\\'], $value);
                }
            }
        }

        return $value;
    }

    private static function cast(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}
