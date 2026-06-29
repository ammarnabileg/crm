<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Authentication\Application;

use HaHireAI\Core\Contracts\UserDirectory;
use HaHireAI\Modules\Users\Application\PasswordHasher;

/**
 * Verifies credentials. Sessions/remember-me are issued by SessionManager.
 * No social login in the initial release (docs/SECURITY_GUIDE.md).
 */
final class Authenticator
{
    public function __construct(
        private readonly UserDirectory $users,
        private readonly PasswordHasher $hasher,
    ) {
    }

    /**
     * Attempt to authenticate. Returns the user row on success, null otherwise.
     *
     * @return array<string, mixed>|null
     */
    public function attempt(string $email, string $password): ?array
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || (string) $user['status'] !== 'active') {
            return null;
        }

        if (! $this->hasher->verify($password, (string) $user['password_hash'])) {
            return null;
        }

        $this->users->recordLogin((string) $user['id']);

        return $user;
    }
}
