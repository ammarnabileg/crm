<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Application;

use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Core\Contracts\AccessControl;

/**
 * The per-request authorization context for the Platform Context (System Owner).
 * The sibling of WorkspaceContext: same single User, a different context. A
 * System Owner is simply a User holding `system.*` permissions — never a
 * separate account type (docs/USER_MODEL.md, docs/PERMISSION_MODEL.md §6).
 */
final class PlatformContext
{
    /** @var list<string> */
    private array $permissions = [];

    private bool $resolved = false;

    public function __construct(
        private readonly AuthContext $auth,
        private readonly AccessControl $authorizer,
    ) {
    }

    /** True when an authenticated user is a System Owner. */
    public function resolve(): bool
    {
        $this->resolved = true;

        if (! $this->auth->check()) {
            return false;
        }

        $userId = (string) $this->auth->id();
        if (! $this->authorizer->userIsSystemOwner($userId)) {
            return false;
        }

        $this->permissions = $this->authorizer->systemPermissionsForUser($userId);

        return true;
    }

    public function userId(): ?string
    {
        return $this->auth->id();
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return $this->permissions;
    }

    public function can(string $permissionKey): bool
    {
        return in_array($permissionKey, $this->permissions, true);
    }
}
