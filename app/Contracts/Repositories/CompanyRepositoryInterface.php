<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Company;

interface CompanyRepositoryInterface extends RepositoryInterface
{
    public function findBySlug(string $slug): ?Company;

    /** @return array<int,array<string,mixed>> Companies a user actively belongs to. */
    public function forUser(int $userId): array;
}
