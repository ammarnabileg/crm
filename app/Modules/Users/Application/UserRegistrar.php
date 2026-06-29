<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Users\Application;

use HaHireAI\Modules\Users\Application\Exceptions\RegistrationException;
use HaHireAI\Modules\Users\Infrastructure\UserRepository;

/**
 * Registers users. Everyone is a `User`; the first user created during
 * installation is the System Owner (docs/USER_MODEL.md).
 */
final class UserRegistrar
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
    ) {
    }

    public function register(string $name, string $email, string $password): string
    {
        $this->guard($name, $email, $password);

        return $this->users->create($name, $email, $this->hasher->hash($password), isSystemOwner: false);
    }

    /** Create the first/only platform operator. Used by the installer. */
    public function createSystemOwner(string $name, string $email, string $password): string
    {
        $this->guard($name, $email, $password);

        return $this->users->create($name, $email, $this->hasher->hash($password), isSystemOwner: true);
    }

    private function guard(string $name, string $email, string $password): void
    {
        if (trim($name) === '') {
            throw new RegistrationException('Name is required.');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RegistrationException('A valid email is required.');
        }

        if (strlen($password) < 8) {
            throw new RegistrationException('Password must be at least 8 characters.');
        }

        if ($this->users->emailExists($email)) {
            throw new RegistrationException('That email is already registered.');
        }
    }
}
