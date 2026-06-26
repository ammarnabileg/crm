<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Password hashing wrapper. Prefers Argon2id when the runtime supports it and
 * transparently falls back to bcrypt, so the platform works across hosts.
 */
final class Hash
{
    public static function make(string $value): string
    {
        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

        $options = $algorithm === PASSWORD_BCRYPT
            ? ['cost' => 12]
            : ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2];

        return password_hash($value, $algorithm, $options);
    }

    public static function verify(string $value, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }

        return password_verify($value, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

        return password_needs_rehash($hash, $algorithm);
    }
}
