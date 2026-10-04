<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Permissions\Application\Authorizer;
use HaHireAI\Modules\Permissions\Application\PlatformRoleService;
use HaHireAI\Modules\Permissions\Domain\PermissionCatalog;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Platform roles & permissions: granular "site managers" beyond is_system_owner. */
final class PlatformRoleTest extends TestCase
{
    private Connection $connection;
    private PlatformRoleService $roles;
    private Authorizer $authorizer;

    protected function setUp(): void
    {
        $this->connection = new Connection([
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_DATABASE') ?: 'hahireai_test',
            'username' => getenv('DB_USERNAME') ?: 'hahireai',
            'password' => getenv('DB_PASSWORD') ?: 'hahireai_pw',
            'charset' => 'utf8mb4',
        ]);

        try {
            $this->connection->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL test database unavailable: ' . $e->getMessage());
        }

        $this->wipe();
        (new MigrationRunner($this->connection, new SchemaBuilder($this->connection)))->run(dirname(__DIR__, 2) . '/database/migrations');
        $this->seedPermissions();
        $this->roles = new PlatformRoleService($this->connection);
        $this->authorizer = new Authorizer($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_role_grants_scoped_platform_permissions_to_a_non_owner(): void
    {
        $user = $this->user(false);
        $this->assertSame([], $this->authorizer->systemPermissionsForUser($user)); // no access yet

        $role = $this->roles->create('Billing Admin', 'Billing only', ['system.dashboard.view', 'system.subscriptions.manage']);
        $this->roles->assignUser($role, $user);

        $perms = $this->authorizer->systemPermissionsForUser($user);
        sort($perms);
        $this->assertSame(['system.dashboard.view', 'system.subscriptions.manage'], $perms);

        // Holder + permission read models.
        $this->assertCount(1, $this->roles->holders($role));
        $this->assertContains('system.subscriptions.manage', $this->roles->permissionKeys($role));

        // Unassign revokes access.
        $this->roles->unassignUser($role, $user);
        $this->assertSame([], $this->authorizer->systemPermissionsForUser($user));
    }

    public function test_system_owner_keeps_full_access_and_updates_resync_permissions(): void
    {
        $owner = $this->user(true);
        $all = $this->authorizer->systemPermissionsForUser($owner);
        $this->assertContains('system.users.manage', $all);
        $this->assertContains('system.roles.manage', $all);

        // Update narrows a role's permissions; the union reflects it.
        $user = $this->user(false);
        $role = $this->roles->create('Support', null, ['system.users.manage', 'system.workspaces.manage']);
        $this->roles->assignUser($role, $user);
        $this->assertContains('system.users.manage', $this->authorizer->systemPermissionsForUser($user));

        $this->roles->update($role, 'Support', 'desc', ['system.workspaces.manage']);
        $perms = $this->authorizer->systemPermissionsForUser($user);
        $this->assertSame(['system.workspaces.manage'], $perms);

        // Delete removes the role and its grants.
        $this->roles->delete($role);
        $this->assertSame([], $this->authorizer->systemPermissionsForUser($user));
    }

    private function seedPermissions(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        foreach (PermissionCatalog::all() as $perm) {
            $this->connection->statement(
                'INSERT INTO permissions (id, `key`, category, description, is_system, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [Ulid::generate(), (string) $perm['key'], (string) ($perm['category'] ?? ''), (string) ($perm['description'] ?? ''), (int) (($perm['category'] ?? '') === 'system' || str_starts_with((string) $perm['key'], 'system.')), $now, $now],
            );
        }
    }

    private function user(bool $systemOwner): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO users (id, name, email, password_hash, is_system_owner, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, 'U', 'u' . substr($id, -6) . '@x.co', 'x', $systemOwner ? 1 : 0, 'active', $now, $now],
        );

        return $id;
    }

    private function wipe(): void
    {
        $this->connection->unprepared('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->connection->select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()') as $row) {
            $this->connection->unprepared('DROP TABLE IF EXISTS `' . $row['t'] . '`');
        }
        $this->connection->unprepared('SET FOREIGN_KEY_CHECKS=1');
    }
}
