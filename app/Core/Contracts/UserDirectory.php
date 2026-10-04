<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * The public surface of the (shared) User identity. Other modules depend on this
 * contract — never on the concrete `Users\Infrastructure\UserRepository` — so the
 * single User identity is consumed as a sanctioned shared service
 * (ARCHITECTURE.md §4). Bound to the repository at boot.
 */
interface UserDirectory
{
    /** @return array<string, mixed>|null */
    public function find(string $id): ?array;

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array;

    public function emailExists(string $email): bool;

    public function create(string $name, string $email, string $passwordHash, bool $isSystemOwner = false): string;

    public function recordLogin(string $id): void;

    public function systemOwnerCount(): int;

    /** Update the user's editable personal data (Candidate profile, spec #4). */
    public function updatePersonal(string $id, string $name, ?string $phone, ?int $yearsExperience, ?int $targetSalary): void;
}
