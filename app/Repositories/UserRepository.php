<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;

/**
 * Repository for the global users table. Email lookups normalize case and run
 * through the model's (non-tenant) query so they respect any model-level scope.
 */
final class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected string $model = User::class;

    public function findByEmail(string $email): ?User
    {
        $row = User::query()->where('email', '=', mb_strtolower(trim($email)))->first();

        return $row ? User::hydrate($row) : null;
    }

    public function emailExists(string $email): bool
    {
        return User::query()->where('email', '=', mb_strtolower(trim($email)))->exists();
    }
}
