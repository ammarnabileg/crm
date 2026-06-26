<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Workspace;

interface WorkspaceRepositoryInterface extends RepositoryInterface
{
    public function findBySlug(string $slug): ?Workspace;

    /** @return array<int,array<string,mixed>> Workspaces a user actively belongs to. */
    public function forUser(int $userId): array;
}
