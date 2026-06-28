<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Users\Infrastructure;

use HaHireAI\Core\Contracts\UserDirectory;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/** Persistence for the single `User` identity (global). Public surface: UserDirectory. */
final class UserRepository implements UserDirectory
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        return $this->connection->selectOne('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->connection->selectOne('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL', [strtolower($email)]);
    }

    public function emailExists(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    public function create(string $name, string $email, string $passwordHash, bool $isSystemOwner = false): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->statement(
            'INSERT INTO users (id, name, email, password_hash, is_system_owner, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $name, strtolower($email), $passwordHash, (int) $isSystemOwner, 'active', $now, $now],
        );

        return $id;
    }

    public function recordLogin(string $id): void
    {
        $this->connection->statement('UPDATE users SET last_login_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), $id]);
    }

    /** Update the candidate's editable personal data (Profile page, spec #4). */
    public function updatePersonal(string $id, string $name, ?string $phone, ?int $yearsExperience, ?int $targetSalary): void
    {
        $this->connection->statement(
            'UPDATE users SET name = ?, phone = ?, years_experience = ?, target_salary = ?, updated_at = ? WHERE id = ?',
            [$name, $phone, $yearsExperience, $targetSalary, gmdate('Y-m-d H:i:s'), $id],
        );
    }

    public function systemOwnerCount(): int
    {
        $row = $this->connection->selectOne('SELECT COUNT(*) AS c FROM users WHERE is_system_owner = 1');

        return (int) ($row['c'] ?? 0);
    }
}
