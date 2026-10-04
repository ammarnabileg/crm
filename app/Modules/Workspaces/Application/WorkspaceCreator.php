<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Contracts\MemberDirectory;
use HaHireAI\Modules\Permissions\Application\RoleService;
use HaHireAI\Modules\Permissions\Domain\PermissionCatalog;
use HaHireAI\Shared\Ulid;

/**
 * Creates a workspace and its owner membership. The owner receives the full set
 * of workspace permissions by DIRECT GRANT — not via a reserved "Owner" role
 * (docs/WORKSPACE_MODEL.md, docs/PERMISSION_MODEL.md). No default roles are
 * created; the owner builds roles themselves.
 *
 * @phpstan-type CreateResult array{workspace_id: string, membership_id: string, slug: string}
 */
final class WorkspaceCreator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly MemberDirectory $memberships,
        private readonly RoleService $roles,
    ) {
    }

    /**
     * @param  array{timezone?: string, locale?: string, currency?: string}  $options
     * @return array{workspace_id: string, membership_id: string, slug: string}
     */
    public function create(string $ownerUserId, string $name, ?string $slug = null, array $options = []): array
    {
        return $this->connection->transaction(function () use ($ownerUserId, $name, $slug, $options): array {
            $workspaceId = Ulid::generate();
            $slug = $this->uniqueSlug($slug ?? $name);
            $now = gmdate('Y-m-d H:i:s');

            $this->connection->statement(
                'INSERT INTO workspaces (id, name, slug, owner_user_id, status, timezone, locale, currency, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $workspaceId, $name, $slug, $ownerUserId, 'active',
                    $options['timezone'] ?? 'UTC', $options['locale'] ?? 'en', $options['currency'] ?? 'USD',
                    $now, $now,
                ],
            );

            $membershipId = $this->memberships->create($workspaceId, $ownerUserId, status: 'active');

            // Owner gets every workspace permission directly (no reserved role).
            $this->roles->grantToMembership($membershipId, PermissionCatalog::workspaceKeys());

            return ['workspace_id' => $workspaceId, 'membership_id' => $membershipId, 'slug' => $slug];
        });
    }

    private function uniqueSlug(string $source): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($source)) ?? '', '-') ?: 'workspace';
        $slug = $base;
        $i = 1;

        while ($this->slugExists($slug)) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }

    private function slugExists(string $slug): bool
    {
        return $this->connection->selectOne('SELECT id FROM workspaces WHERE slug = ?', [$slug]) !== null;
    }
}
