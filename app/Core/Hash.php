<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Password hashing wrapper. Prefers Argon2id when the runtime can actually produce
 * it and transparently falls back to bcrypt, so the platform works across hosts —
 * including ones whose bundled libargon2 only supports a single thread (where a
 * threads cost > 1 fails with "A thread value other than 1 is not supported by this
 * implementation"). `threads` is therefore pinned to 1, and make() degrades to
 * bcrypt if Argon2id cannot be produced for any reason — account creation never
 * hard-fails on a host quirk.
 */
final class Hash
{
    /**
     * Argon2id cost. threads MUST be 1 — many libargon2 builds (common on shared
     * hosting) reject any higher value outright.
     */
    private const ARGON_OPTIONS = ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1];

    private const BCRYPT_OPTIONS = ['cost' => 12];

    public static function make(string $value): string
    {
        // Try Argon2id first, but never let a host-specific quirk break account
        // creation: password_hash() emits a warning + returns false on an
        // unsupported option (which the app's error handler would otherwise turn
        // into an exception), so suppress it and fall back to bcrypt (always built in).
        if (defined('PASSWORD_ARGON2ID')) {
            $hash = @password_hash($value, PASSWORD_ARGON2ID, self::ARGON_OPTIONS);
            if (is_string($hash) && $hash !== '') {
                return $hash;
            }
        }

        return password_hash($value, PASSWORD_BCRYPT, self::BCRYPT_OPTIONS);
    }

    public static function verify(string $value, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }

        return password_verify($value, $hash);
    }

    /**
     * Whether a stored hash should be upgraded. Each algorithm is checked against
     * its OWN current policy, so a bcrypt hash on an Argon2-less host never churns
     * (re-hashing on every login to an algorithm the host cannot produce).
     */
    public static function needsRehash(string $hash): bool
    {
        if ($hash === '') {
            return true;
        }

        $name = (string) (password_get_info($hash)['algoName'] ?? 'unknown');

        return match ($name) {
            'argon2id' => defined('PASSWORD_ARGON2ID')
                && password_needs_rehash($hash, PASSWORD_ARGON2ID, self::ARGON_OPTIONS),
            'bcrypt'   => password_needs_rehash($hash, PASSWORD_BCRYPT, self::BCRYPT_OPTIONS),
            default    => true,
        };
    }
}
