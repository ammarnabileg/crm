<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Users\Application;

/** Argon2id password hashing (docs/SECURITY_GUIDE.md). */
final class PasswordHasher
{
    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_ARGON2ID);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }
}
