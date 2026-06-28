<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Application;

use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Core\Contracts\MemberDirectory;
use HaHireAI\Core\Contracts\AccessControl;

/**
 * Resolves the current (user, workspace, membership, permissions) for a request
 * and answers can() checks — the per-request authorization context controllers
 * use (docs/PERMISSION_MODEL.md, docs/NAVIGATION_ARCHITECTURE.md).
 */
final class WorkspaceContext
{
    /** @var array<string, mixed>|null */
    private ?array $workspace = null;

    /** @var array<string, mixed>|null */
    private ?array $membership = null;

    /** @var list<string> */
    private array $permissions = [];

    private bool $resolved = false;

    public function __construct(
        private readonly AuthContext $auth,
        private readonly MemberDirectory $memberships,
        private readonly AccessControl $authorizer,
    ) {
    }

    /** True if an authenticated user has a current workspace. */
    public function resolve(): bool
    {
        $this->resolved = true;

        if (! $this->auth->check()) {
            return false;
        }

        $userId = (string) $this->auth->id();
        $list = $this->memberships->workspacesForUser($userId);

        if ($list === []) {
            return false;
        }

        $currentId = $this->auth->currentWorkspaceId();
        $this->workspace = null;

        foreach ($list as $w) {
            if ((string) $w['id'] === $currentId) {
                $this->workspace = $w;
                break;
            }
        }

        $this->workspace ??= $list[0];
        $this->auth->setCurrentWorkspace((string) $this->workspace['id']);

        $this->membership = $this->memberships->find((string) $this->workspace['id'], $userId);
        $this->permissions = $this->membership !== null
            ? $this->authorizer->permissionsForMembership((string) $this->membership['id'])
            : [];

        $this->recordActivity();

        return true;
    }

    /** Stamp last activity at most once per minute to keep the hot path cheap. */
    private function recordActivity(): void
    {
        if ($this->membership === null) {
            return;
        }

        $last = $this->membership['last_activity_at'] ?? null;
        if ($last === null || (string) $last < gmdate('Y-m-d H:i:s', time() - 60)) {
            $this->memberships->touchActivity((string) $this->membership['id']);
            $this->membership['last_activity_at'] = gmdate('Y-m-d H:i:s');
        }
    }

    /** @return array<string, mixed>|null */
    public function workspace(): ?array
    {
        return $this->workspace;
    }

    public function workspaceId(): ?string
    {
        return $this->workspace !== null ? (string) $this->workspace['id'] : null;
    }

    public function membershipId(): ?string
    {
        return $this->membership !== null ? (string) $this->membership['id'] : null;
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
