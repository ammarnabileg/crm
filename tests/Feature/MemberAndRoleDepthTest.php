<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Memberships\Application\MembershipService;
use HaHireAI\Modules\Permissions\Application\PermissionSeeder;
use HaHireAI\Modules\Permissions\Application\RoleService;
use HaHireAI\Modules\Permissions\Infrastructure\PermissionRepository;
use HaHireAI\Modules\Workspaces\Application\WorkspaceCreator;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * Sprint 3.3f — member status actions (suspend/activate/remove) + enriched
 * listing, and role clone/usage/delete depth.
 */
final class MemberAndRoleDepthTest extends TestCase
{
    private Connection $connection;
    private MembershipService $members;
    private RoleService $roles;
    private WorkspaceCreator $creator;

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

        $permRepo = new PermissionRepository($this->connection);
        (new PermissionSeeder($permRepo))->seed();
        $this->members = new MembershipService($this->connection);
        $this->roles = new RoleService($this->connection, $permRepo);
        $this->creator = new WorkspaceCreator($this->connection, $this->members, $this->roles);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_member_status_lifecycle_and_enriched_listing(): void
    {
        [$ws] = $this->workspace();
        $userB = $this->user('teammate@x.co');
        $roleId = $this->roles->createRole($ws, 'Recruiter', ['member.view', 'job.view']);
        $membershipB = $this->members->create($ws, $userB);
        $this->roles->assignRoleToMembership($membershipB, $roleId);

        // Enriched listing carries identity, status, role names and activity columns.
        $rows = $this->members->membersForWorkspace($ws);
        $this->assertCount(2, $rows);
        $teammate = $this->rowFor($rows, $userB);
        $this->assertSame('active', $teammate['status']);
        $this->assertSame('Recruiter', $teammate['roles']);
        $this->assertArrayHasKey('last_login_at', $teammate);
        $this->assertArrayHasKey('last_activity_at', $teammate);

        // Suspend → reactivate.
        $this->assertTrue($this->members->setStatus($ws, $membershipB, 'suspended'));
        $this->assertSame('suspended', $this->rowFor($this->members->membersForWorkspace($ws), $userB)['status']);
        $this->assertTrue($this->members->setStatus($ws, $membershipB, 'active'));
        $this->assertSame('active', $this->rowFor($this->members->membersForWorkspace($ws), $userB)['status']);

        // Activity stamp moves from null to a value.
        $this->assertNull($this->rowFor($this->members->membersForWorkspace($ws), $userB)['last_activity_at']);
        $this->members->touchActivity($membershipB);
        $this->assertNotNull($this->rowFor($this->members->membersForWorkspace($ws), $userB)['last_activity_at']);

        // Tenant guard: a foreign workspace cannot touch this membership.
        $this->assertFalse($this->members->setStatus(Ulid::generate(), $membershipB, 'suspended'));

        // Remove → soft deleted, drops out of the listing, find() returns null.
        $this->assertTrue($this->members->remove($ws, $membershipB));
        $this->assertCount(1, $this->members->membersForWorkspace($ws));
        $this->assertNull($this->members->find($ws, $userB));
    }

    public function test_role_clone_usage_and_delete(): void
    {
        [$ws] = $this->workspace();
        $source = $this->roles->createRole($ws, 'Hiring Manager', ['member.view', 'role.view', 'job.view']);

        // Clone copies the permission set into a new role.
        $clone = $this->roles->cloneRole($ws, $source, 'Hiring Manager (copy)');
        $this->assertNotSame($source, $clone);
        $this->assertEqualsCanonicalizing(
            $this->roles->permissionKeysForRole($source),
            $this->roles->permissionKeysForRole($clone),
        );

        // Usage reflects assignments; with-usage listing reports members + permission counts.
        $this->assertSame(0, $this->roles->usageCount($source));
        $membershipB = $this->members->create($ws, $this->user('m@x.co'));
        $this->roles->assignRoleToMembership($membershipB, $source);
        $this->assertSame(1, $this->roles->usageCount($source));

        $withUsage = $this->roles->rolesForWorkspaceWithUsage($ws);
        $sourceRow = $this->rowById($withUsage, $source);
        $this->assertSame(1, (int) $sourceRow['members']);
        $this->assertSame(3, (int) $sourceRow['permissions']);

        // Tenant-guarded lookup.
        $this->assertNotNull($this->roles->findRole($ws, $source));
        $this->assertNull($this->roles->findRole(Ulid::generate(), $source));

        // Deleting the unused clone removes it.
        $this->roles->deleteRole($ws, $clone);
        $this->assertNull($this->roles->findRole($ws, $clone));
        $this->assertSame(0, $this->roles->usageCount($clone));
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @return array<string,mixed>
     */
    private function rowFor(array $rows, string $userId): array
    {
        foreach ($rows as $row) {
            if ((string) $row['user_id'] === $userId) {
                return $row;
            }
        }

        $this->fail('Member row not found for user ' . $userId);
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @return array<string,mixed>
     */
    private function rowById(array $rows, string $roleId): array
    {
        foreach ($rows as $row) {
            if ((string) $row['id'] === $roleId) {
                return $row;
            }
        }

        $this->fail('Role row not found for id ' . $roleId);
    }

    /** @return array{0:string,1:string} */
    private function workspace(): array
    {
        $owner = $this->user('owner@x.co');
        $r = $this->creator->create($owner, 'Acme', null, []);

        return [$r['workspace_id'], $owner];
    }

    private function user(string $email): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO users (id, name, email, password_hash, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, 'User', substr($id, -4) . '.' . $email, 'x', 'active', $now, $now],
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
