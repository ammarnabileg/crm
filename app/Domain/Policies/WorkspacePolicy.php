<?php

declare(strict_types=1);

namespace App\Domain\Policies;

use App\Models\Workspace;
use App\Models\User;
use App\Services\Rbac\AccessControl;
use App\Services\Tenancy\TenantManager;

/**
 * Authorization policy for Workspace objects. Encapsulates context-aware rules
 * that a permission flag alone cannot express — ownership and tenant membership
 * (docs/47 EAS-11). Super admins are short-circuited by AccessControl before a
 * policy runs, so these methods only decide for ordinary users.
 */
final class WorkspacePolicy
{
    public function __construct(
        private readonly AccessControl $access,
        private readonly TenantManager $tenant,
    ) {
    }

    /** A user may view a workspace they actively belong to. */
    public function view(User $user, Workspace $workspace): bool
    {
        return $user->membershipFor((int) $workspace->getKey()) !== null;
    }

    /**
     * A user may edit a workspace if they own it, or hold workspace.update within
     * that workspace's active context.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        if ($user->ownsWorkspace((int) $workspace->getKey())) {
            return true;
        }

        return $this->tenant->id() === (int) $workspace->getKey()
            && $this->access->hasPermission($user, 'workspace.update');
    }

    /** Only the owner may delete (archive) a workspace. */
    public function delete(User $user, Workspace $workspace): bool
    {
        return $user->ownsWorkspace((int) $workspace->getKey());
    }
}
