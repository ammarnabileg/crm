<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\WorkspaceRepositoryInterface;
use App\Models\Workspace;

/**
 * Repository for workspaces (tenants). Workspaces live in a global table; access is
 * always mediated through memberships, so cross-workspace lookups use the model's
 * unscoped builder explicitly.
 */
final class WorkspaceRepository extends BaseRepository implements WorkspaceRepositoryInterface
{
    protected string $model = Workspace::class;

    public function findBySlug(string $slug): ?Workspace
    {
        $row = Workspace::withoutTenantScope()->where('slug', '=', $slug)->first();

        return $row ? Workspace::hydrate($row) : null;
    }

    public function forUser(int $userId): array
    {
        return Workspace::withoutTenantScope()
            ->select('workspaces.*')
            ->join('memberships', 'memberships.workspace_id', '=', 'workspaces.id')
            ->where('memberships.user_id', '=', $userId)
            ->where('memberships.status', '=', 'active')
            ->orderBy('workspaces.created_at', 'desc')
            ->get();
    }
}
