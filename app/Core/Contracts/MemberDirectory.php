<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * The public surface of workspace membership. Other modules depend on this
 * contract — never on the concrete `Memberships\Application\MembershipService` —
 * so the User↔Workspace link is consumed as a sanctioned shared service
 * (ARCHITECTURE.md §4). Bound to MembershipService at boot.
 */
interface MemberDirectory
{
    public function create(string $workspaceId, string $userId, string $status = 'active', ?string $invitedBy = null): string;

    /** @return array<string, mixed>|null */
    public function findById(string $membershipId): ?array;

    /** @return array<string, mixed>|null */
    public function find(string $workspaceId, string $userId): ?array;

    public function countForWorkspace(string $workspaceId): int;

    /** @return list<array<string, mixed>> workspaces a user belongs to */
    public function workspacesForUser(string $userId): array;

    /** @return list<array<string, mixed>> */
    public function workspacesForUserDetailed(string $userId): array;

    /** @return list<array<string, mixed>> members of a workspace with their role names + activity */
    public function membersForWorkspace(string $workspaceId): array;

    /** @return list<string> user ids of active members holding the given role (for assignment fan-out) */
    public function membersWithRole(string $workspaceId, string $roleId): array;

    public function setStatus(string $workspaceId, string $membershipId, string $status): bool;

    public function remove(string $workspaceId, string $membershipId): bool;

    public function touchActivity(string $membershipId): void;
}
