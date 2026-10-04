<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Memberships\Application\Exceptions\InvitationException;
use HaHireAI\Modules\Memberships\Application\InvitationService;
use HaHireAI\Modules\Memberships\Application\MembershipService;
use HaHireAI\Modules\Permissions\Application\RoleService;
use HaHireAI\Modules\Permissions\Infrastructure\PermissionRepository;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Accept-first invitations + invite management (InvitationService). */
final class InvitationFlowTest extends TestCase
{
    private Connection $connection;
    private InvitationService $invitations;
    private MembershipService $members;
    private string $ws = '';

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
        $this->members = new MembershipService($this->connection);
        $this->invitations = new InvitationService(
            $this->connection,
            $this->members,
            new RoleService($this->connection, new PermissionRepository($this->connection)),
        );
        $this->ws = $this->workspace();
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_pending_invites_surface_by_email_and_accept_creates_membership(): void
    {
        $this->invitations->invite($this->ws, 'Pat@Example.com', [], null, 14);

        $pending = $this->invitations->pendingForEmail('pat@example.com');
        $this->assertCount(1, $pending);
        $this->assertSame('Acme', $pending[0]['workspace_name']);

        $user = $this->user('pat@example.com');
        $membershipId = $this->invitations->acceptOwn($pending[0]['id'], $user, 'pat@example.com');

        $this->assertNotSame('', $membershipId);
        $this->assertNotNull($this->members->find($this->ws, $user), 'membership exists after accept');
        $this->assertSame([], $this->invitations->pendingForEmail('pat@example.com'), 'no longer pending');
    }

    public function test_cannot_accept_an_invitation_addressed_to_someone_else(): void
    {
        $invite = $this->invitations->invite($this->ws, 'owner-target@example.com');
        $intruder = $this->user('intruder@example.com');

        $this->expectException(InvitationException::class);
        $this->invitations->acceptOwn($invite['id'], $intruder, 'intruder@example.com');
    }

    public function test_decline_removes_it_from_pending(): void
    {
        $invite = $this->invitations->invite($this->ws, 'decliner@example.com');

        $this->invitations->declineOwn($invite['id'], 'decliner@example.com');

        $this->assertSame([], $this->invitations->pendingForEmail('decliner@example.com'));
    }

    public function test_manage_invitation_roles_and_revoke(): void
    {
        $invite = $this->invitations->invite($this->ws, 'managed@example.com', ['role-1']);

        $this->invitations->updateRoles($this->ws, $invite['id'], ['role-2', 'role-3']);
        $this->assertSame(['role-2', 'role-3'], $this->invitations->pendingForEmail('managed@example.com')[0]['role_ids']);

        $this->invitations->revoke($this->ws, $invite['id']);
        $this->assertSame([], $this->invitations->pendingForEmail('managed@example.com'), 'revoked invite is no longer pending');
        $this->assertSame([], $this->invitations->forWorkspace($this->ws), 'no pending invitations remain');
    }

    private function user(string $email): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement('INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$id, 'U', strtolower($email), 'x', $now, $now]);

        return $id;
    }

    private function workspace(): string
    {
        $owner = $this->user('owner' . bin2hex(random_bytes(3)) . '@x.co');
        $ws = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement('INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$ws, 'Acme', 'acme-' . substr($ws, -6), $owner, $now, $now]);

        return $ws;
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
