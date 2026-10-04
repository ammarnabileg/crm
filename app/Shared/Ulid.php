<?php

declare(strict_types=1);

namespace HaHireAI\Shared;

/**
 * ULID generator — the project's canonical identifier (CHAR(26), time-sortable,
 * Crockford base32). Generated in application code, never by the database
 * (no AUTO_INCREMENT). See docs/DATABASE_GUIDE.md §3.
 */
final class Ulid
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** Generate a new 26-character ULID. */
    public static function generate(): string
    {
        return self::encodeTime() . self::encodeRandomness();
    }

    public static function isValid(string $value): bool
    {
        return strlen($value) === 26
            && strspn($value, self::ALPHABET) === 26;
    }

    private static function encodeTime(): string
    {
        $time = (int) (microtime(true) * 1000);
        $chars = '';

        for ($i = 0; $i < 10; $i++) {
            $chars = self::ALPHABET[$time % 32] . $chars;
            $time = intdiv($time, 32);
        }

        return $chars;
    }

    private static function encodeRandomness(): string
    {
        $chars = '';

        for ($i = 0; $i < 16; $i++) {
            $chars .= self::ALPHABET[random_int(0, 31)];
        }

        return $chars;
    }
}
