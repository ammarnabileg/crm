<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * The public surface of authorization. Other modules depend on this contract —
 * never on the concrete `Permissions\Application\Authorizer` — so effective-
 * permission resolution is a sanctioned shared service (ARCHITECTURE.md §4).
 * Deny by default; checks reference permission KEYS, never role names.
 * Bound to Authorizer at boot.
 */
interface AccessControl
{
    /**
     * Effective workspace permission keys for a membership.
     *
     * @return list<string>
     */
    public function permissionsForMembership(string $membershipId): array;

    public function membershipCan(string $membershipId, string $permissionKey): bool;

    /**
     * Effective platform (system.*) permission keys for a user.
     *
     * @return list<string>
     */
    public function systemPermissionsForUser(string $userId): array;

    public function userIsSystemOwner(string $userId): bool;
}
