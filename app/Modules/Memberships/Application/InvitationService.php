<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Memberships\Application;

use HaHireAI\Core\Contracts\InvitationInbox;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Memberships\Application\Exceptions\InvitationException;
use HaHireAI\Modules\Permissions\Application\RoleService;
use HaHireAI\Shared\Ulid;

/** Workspace invitations (docs/INVITATION_SYSTEM.md, STATE_DIAGRAMS §8). */
final class InvitationService implements InvitationInbox
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

            return $this->acceptRow($invitation, $userId);
        });
    }

    /**
     * Accept-first: accept a specific pending invitation the signed-in user owns
     * (matched by their email), without needing the emailed code link. Returns
     * the new membership id.
     */
    public function acceptOwn(string $invitationId, string $userId, string $email): string
    {
        return $this->connection->transaction(function () use ($invitationId, $userId, $email): string {
            $invitation = $this->connection->selectOne('SELECT * FROM invitations WHERE id = ?', [$invitationId]);
            if ($invitation === null || strtolower((string) $invitation['email']) !== strtolower($email)) {
                throw new InvitationException('Invitation not found.');
            }

            return $this->acceptRow($invitation, $userId);
        });
    }

    /** Decline a pending invitation the signed-in user owns (matched by email). */
    public function declineOwn(string $invitationId, string $email): void
    {
        $invitation = $this->connection->selectOne('SELECT * FROM invitations WHERE id = ?', [$invitationId]);
        if ($invitation === null || strtolower((string) $invitation['email']) !== strtolower($email)) {
            throw new InvitationException('Invitation not found.');
        }
        if ((string) $invitation['status'] !== 'pending') {
            return;
        }
        $this->connection->statement(
            'UPDATE invitations SET status = ?, updated_at = ? WHERE id = ?',
            ['rejected', gmdate('Y-m-d H:i:s'), $invitation['id']],
        );
    }

    public function pendingForEmail(string $email): array
    {
        $rows = $this->connection->select(
            "SELECT i.id, i.workspace_id, i.email, i.role_ids, i.created_at, i.expires_at,
                    w.name AS workspace_name, u.name AS inviter_name
               FROM invitations i
               JOIN workspaces w ON w.id = i.workspace_id AND w.deleted_at IS NULL
          LEFT JOIN users u ON u.id = i.invited_by
              WHERE i.email = ? AND i.status = 'pending'
                AND (i.expires_at IS NULL OR i.expires_at > ?)
           ORDER BY i.created_at DESC",
            [strtolower($email), gmdate('Y-m-d H:i:s')],
        );

        return array_map(static function (array $r): array {
            /** @var list<string> $roleIds */
            $roleIds = json_decode((string) ($r['role_ids'] ?? '[]'), true) ?: [];

            return [
                'id' => (string) $r['id'],
                'workspace_id' => (string) $r['workspace_id'],
                'workspace_name' => (string) $r['workspace_name'],
                'email' => (string) $r['email'],
                'role_ids' => array_map('strval', $roleIds),
                'inviter_name' => $r['inviter_name'] !== null ? (string) $r['inviter_name'] : null,
                'created_at' => (string) $r['created_at'],
                'expires_at' => $r['expires_at'] !== null ? (string) $r['expires_at'] : null,
            ];
        }, $rows);
    }

    /**
     * Invitations for a workspace (for the Members management view).
     *
     * @return list<array<string, mixed>>
     */
    public function forWorkspace(string $workspaceId, bool $pendingOnly = true): array
    {
        $where = $pendingOnly ? "AND i.status = 'pending'" : '';

        return $this->connection->select(
            "SELECT i.id, i.email, i.status, i.role_ids, i.created_at, i.expires_at, u.name AS inviter_name
               FROM invitations i
          LEFT JOIN users u ON u.id = i.invited_by
              WHERE i.workspace_id = ? {$where}
           ORDER BY i.created_at DESC",
            [$workspaceId],
        );
    }

    /**
     * Replace the roles a pending invitation will grant on acceptance.
     *
     * @param  list<string>  $roleIds
     */
    public function updateRoles(string $workspaceId, string $invitationId, array $roleIds): void
    {
        $this->connection->statement(
            "UPDATE invitations SET role_ids = ?, updated_at = ? WHERE id = ? AND workspace_id = ? AND status = 'pending'",
            [json_encode(array_values($roleIds)), gmdate('Y-m-d H:i:s'), $invitationId, $workspaceId],
        );
    }

    /** Revoke (cancel) a pending invitation. */
    public function revoke(string $workspaceId, string $invitationId): void
    {
        $this->connection->statement(
            "UPDATE invitations SET status = 'cancelled', updated_at = ? WHERE id = ? AND workspace_id = ? AND status = 'pending'",
            [gmdate('Y-m-d H:i:s'), $invitationId, $workspaceId],
        );
    }

    /**
     * Create the membership + assign roles for a validated invitation row, then
     * mark it accepted. Shared by accept() (by code) and acceptOwn() (by email).
     *
     * @param  array<string, mixed>|null  $invitation
     */
    private function acceptRow(?array $invitation, string $userId): string
    {
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
    }
}
