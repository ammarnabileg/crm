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

/** Gap A — roles can be edited (name + permissions) and list their holders. */
final class RoleEditTest extends TestCase
{
    private Connection $connection;
    private RoleService $roles;
    private MembershipService $members;
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

    public function test_update_replaces_name_and_permissions(): void
    {
        $ws = $this->workspace();
        $roleId = $this->roles->createRole($ws, 'Recruiter', ['job.view', 'member.view']);

        $this->assertTrue($this->roles->updateRole($ws, $roleId, 'Senior Recruiter', ['job.view', 'job.create', 'candidate.view']));

        $role = $this->roles->findRole($ws, $roleId);
        $this->assertSame('Senior Recruiter', $role['name']);
        $this->assertEqualsCanonicalizing(
            ['job.view', 'job.create', 'candidate.view'],
            $this->roles->permissionKeysForRole($roleId),
        );

        // Tenant guard: a foreign workspace cannot edit this role.
        $this->assertFalse($this->roles->updateRole(Ulid::generate(), $roleId, 'Hacked', []));
    }

    public function test_members_with_role_lists_holders(): void
    {
        $ws = $this->workspace();
        $roleId = $this->roles->createRole($ws, 'Interviewer', ['interview.view']);

        $this->assertSame([], $this->roles->membersWithRole($ws, $roleId));

        $userId = $this->user('holder@x.co');
        $membershipId = $this->members->create($ws, $userId);
        $this->roles->assignRoleToMembership($membershipId, $roleId);

        $holders = $this->roles->membersWithRole($ws, $roleId);
        $this->assertCount(1, $holders);
        $this->assertSame($userId, (string) $holders[0]['user_id']);
        $this->assertSame(1, $this->roles->usageCount($roleId));
    }

    private function workspace(): string
    {
        $owner = $this->user('owner@x.co');

        return $this->creator->create($owner, 'Acme', null, [])['workspace_id'];
    }

    private function user(string $email): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement('INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$id, 'U', substr($id, -4) . '.' . $email, 'x', $now, $now]);

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
