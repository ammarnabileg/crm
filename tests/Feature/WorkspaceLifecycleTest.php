<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Memberships\Application\MembershipService;
use HaHireAI\Modules\Permissions\Application\Authorizer;
use HaHireAI\Modules\Permissions\Application\RoleService;
use HaHireAI\Modules\Permissions\Domain\PermissionCatalog;
use HaHireAI\Modules\Permissions\Infrastructure\PermissionRepository;
use HaHireAI\Modules\Workspaces\Application\WorkspaceCreator;
use HaHireAI\Modules\Workspaces\Application\WorkspaceLifecycleService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Sprint 2 — workspace lifecycle: archive/restore/suspend/resume/transfer-ownership. */
final class WorkspaceLifecycleTest extends TestCase
{
    private Connection $connection;
    private WorkspaceCreator $creator;
    private WorkspaceLifecycleService $lifecycle;
    private MembershipService $memberships;
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

        $permRepo = new PermissionRepository($this->connection);
        (new \HaHireAI\Modules\Permissions\Application\PermissionSeeder($permRepo))->seed();
        $this->memberships = new MembershipService($this->connection);
        $roles = new RoleService($this->connection, $permRepo);
        $this->authorizer = new Authorizer($this->connection);
        $this->creator = new WorkspaceCreator($this->connection, $this->memberships, $roles);
        $this->lifecycle = new WorkspaceLifecycleService($this->connection, $this->memberships, $roles);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_archive_then_restore(): void
    {
        [$ws] = $this->workspace();
        $this->lifecycle->archive($ws);
        $this->assertSame('archived', $this->wsStatus($ws));
        $this->assertNotNull($this->lifecycle->find($ws)['archived_at']);

        $this->lifecycle->restore($ws);
        $this->assertSame('active', $this->wsStatus($ws));
        $this->assertNull($this->lifecycle->find($ws)['archived_at']);
    }

    public function test_suspend_then_resume(): void
    {
        [$ws] = $this->workspace();
        $this->lifecycle->suspend($ws);
        $this->assertSame('suspended', $this->wsStatus($ws));

        $this->lifecycle->resume($ws);
        $this->assertSame('active', $this->wsStatus($ws));
    }

    public function test_transfer_ownership_makes_new_owner_a_full_member(): void
    {
        [$ws, $owner] = $this->workspace();
        $newOwner = $this->user('new@x.co');

        $this->assertTrue($this->lifecycle->transferOwnership($ws, $newOwner));

        // owner_user_id updated.
        $this->assertSame($newOwner, (string) $this->lifecycle->find($ws)['owner_user_id']);
        // new owner is a member with the full workspace permission set.
        $m = $this->memberships->find($ws, $newOwner);
        $this->assertNotNull($m);
        $perms = $this->authorizer->permissionsForMembership((string) $m['id']);
        $this->assertSame(count(PermissionCatalog::workspaceKeys()), count($perms));
    }

    public function test_transfer_ownership_validation(): void
    {
        [$ws, $owner] = $this->workspace();
        $this->assertFalse($this->lifecycle->transferOwnership($ws, $owner));        // same owner
        $this->assertFalse($this->lifecycle->transferOwnership($ws, 'nonexistent'));  // unknown user
        $this->assertFalse($this->lifecycle->transferOwnership('nope', $this->user('z@x.co')));
    }

    private function wsStatus(string $ws): string
    {
        return (string) $this->connection->selectOne('SELECT status FROM workspaces WHERE id = ?', [$ws])['status'];
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
            [$id, 'User', $email . '.' . substr($id, -4), 'x', 'active', $now, $now],
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
