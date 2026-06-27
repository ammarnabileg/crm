<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Memberships\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Memberships\Application\Exceptions\InvitationException;
use HaHireAI\Modules\Permissions\Application\RoleService;
use HaHireAI\Shared\Ulid;

/** Workspace invitations (docs/INVITATION_SYSTEM.md, STATE_DIAGRAMS §8). */
final class InvitationService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly MembershipService $memberships,
        private readonly RoleService $roles,
    ) {
    }

    /**
     * Create an invitation. Returns its id and the shareable code.
     *
     * @param  list<string>  $roleIds
     * @return array{id: string, code: string}
     */
    public function invite(string $workspaceId, string $email, array $roleIds = [], ?string $invitedBy = null, int $ttlDays = 14): array
    {
        $id = Ulid::generate();
        $code = strtoupper(bin2hex(random_bytes(8)));
        $now = gmdate('Y-m-d H:i:s');
        $expires = gmdate('Y-m-d H:i:s', time() + $ttlDays * 86400);

        $this->connection->statement(
            'INSERT INTO invitations (id, workspace_id, email, code, role_ids, status, invited_by, expires_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, strtolower($email), $code, json_encode(array_values($roleIds)), 'pending', $invitedBy, $expires, $now, $now],
        );

        return ['id' => $id, 'code' => $code];
    }

    /**
     * Accept an invitation by code for a given user. Creates the membership and
     * assigns the invited roles. Returns the membership id.
     */
    public function accept(string $code, string $userId): string
    {
        return $this->connection->transaction(function () use ($code, $userId): string {
            $invitation = $this->connection->selectOne('SELECT * FROM invitations WHERE code = ?', [$code]);

            if ($invitation === null) {
                throw new InvitationException('Invitation not found.');
            }

            if ((string) $invitation['status'] !== 'pending') {
                throw new InvitationException('Invitation is no longer pending.');
            }

            if ($invitation['expires_at'] !== null && strtotime((string) $invitation['expires_at']) < time()) {
                $this->connection->statement('UPDATE invitations SET status = ? WHERE id = ?', ['expired', $invitation['id']]);
                throw new InvitationException('Invitation has expired.');
            }

            $workspaceId = (string) $invitation['workspace_id'];
            $membershipId = $this->memberships->create($workspaceId, $userId, status: 'active', invitedBy: $invitation['invited_by'] ?? null);

            /** @var list<string> $roleIds */
            $roleIds = json_decode((string) ($invitation['role_ids'] ?? '[]'), true) ?: [];
            foreach ($roleIds as $roleId) {
                $this->roles->assignRoleToMembership($membershipId, (string) $roleId);
            }

            $this->connection->statement(
                'UPDATE invitations SET status = ?, accepted_at = ?, updated_at = ? WHERE id = ?',
                ['accepted', gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s'), $invitation['id']],
            );

            return $membershipId;
        });
    }
}
