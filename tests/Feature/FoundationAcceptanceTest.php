<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Authentication\Application\Authenticator;
use HaHireAI\Modules\Installer\Application\Installer;
use HaHireAI\Modules\Memberships\Application\InvitationService;
use HaHireAI\Modules\Memberships\Application\MembershipService;
use HaHireAI\Modules\Navigation\Application\SidebarBuilder;
use HaHireAI\Modules\Permissions\Application\Authorizer;
use HaHireAI\Modules\Permissions\Application\PermissionSeeder;
use HaHireAI\Modules\Permissions\Application\RoleService;
use HaHireAI\Modules\Permissions\Infrastructure\PermissionRepository;
use HaHireAI\Modules\Users\Application\PasswordHasher;
use HaHireAI\Modules\Users\Application\UserRegistrar;
use HaHireAI\Modules\Users\Infrastructure\UserRepository;
use PHPUnit\Framework\TestCase;

/**
 * Phase 8 FINAL ACCEPTANCE — the full foundation scenario end-to-end against a
 * live MySQL 8 database: install → System Owner → register → login → create
 * workspace → custom role → invite → accept → authorize → dynamic sidebar.
 */
final class FoundationAcceptanceTest extends TestCase
{
    private Connection $connection;
    private SchemaBuilder $schema;
    private string $lockPath;

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

        $this->schema = new SchemaBuilder($this->connection);
        $this->wipe();
        $this->lockPath = sys_get_temp_dir() . '/install_' . bin2hex(random_bytes(4)) . '.lock';
    }

    protected function tearDown(): void
    {
        $this->wipe();
        if (is_file($this->lockPath)) {
            unlink($this->lockPath);
        }
    }

    public function test_full_foundation_acceptance_scenario(): void
    {
        $installer = $this->installer();
        $authorizer = new Authorizer($this->connection);
        $sidebar = new SidebarBuilder();

        // 1–3. Zero-touch install: migrations + permission catalog + first System Owner.
        $this->assertFalse($installer->isInstalled());
        $this->assertTrue($installer->requirementsSatisfied());

        $result = $installer->install([
            'name' => 'Platform Admin',
            'email' => 'owner@hahire.ai',
            'password' => 'super-secret-pw',
        ]);

        $this->assertNotEmpty($result['migrations']);
        $this->assertGreaterThan(40, $result['permissions']);
        $this->assertTrue($installer->isInstalled());
        $ownerId = $result['system_owner_id'];

        // 4. System Owner holds system.* permissions and sees the Platform Context.
        $this->assertTrue($authorizer->userIsSystemOwner($ownerId));
        $systemPerms = $authorizer->systemPermissionsForUser($ownerId);
        $this->assertContains('system.workspaces.manage', $systemPerms);
        $this->assertNotEmpty($sidebar->labels('platform', $systemPerms));

        // 5. Register a normal user and log in.
        $registrar = $this->registrar();
        $authenticator = new Authenticator($this->userRepository(), new PasswordHasher());

        $saraId = $registrar->register('Sara', 'sara@example.com', 'sara-password');
        $this->assertNull($authenticator->attempt('sara@example.com', 'wrong'));
        $this->assertSame($saraId, (string) $authenticator->attempt('sara@example.com', 'sara-password')['id']);

        // 6. With no workspace, Sara belongs to none.
        $memberships = new MembershipService($this->connection);
        $this->assertCount(0, $memberships->workspacesForUser($saraId));

        // 7. Sara creates a workspace; she becomes owner.
        $workspaces = $this->workspaceCreator();
        $acme = $workspaces->create($saraId, 'Acme Inc');
        $this->assertCount(1, $memberships->workspacesForUser($saraId));

        // 8. The owner holds ALL workspace permissions by direct grant (no reserved role).
        $ownerMembership = $acme['membership_id'];
        $this->assertTrue($authorizer->membershipCan($ownerMembership, 'workspace.delete'));
        $this->assertTrue($authorizer->membershipCan($ownerMembership, 'job.create'));

        // 9. Sara builds a CUSTOM role with a subset of permissions.
        $roles = $this->roleService();
        $recruiterRole = $roles->createRole($acme['workspace_id'], 'Recruiter', ['job.view', 'job.create', 'candidate.view']);

        // 10. Invite a second user; they register and accept.
        $omarId = $registrar->register('Omar', 'omar@example.com', 'omar-password');
        $invitations = new InvitationService($this->connection, $memberships, $roles);
        $invite = $invitations->invite($acme['workspace_id'], 'omar@example.com', [$recruiterRole], $saraId);
        $omarMembership = $invitations->accept($invite['code'], $omarId);

        // 11. Authorization is by permission, not role name.
        $this->assertTrue($authorizer->membershipCan($omarMembership, 'job.create'));
        $this->assertFalse($authorizer->membershipCan($omarMembership, 'workspace.delete'));
        $this->assertFalse($authorizer->membershipCan($omarMembership, 'billing.view'));

        // 12. The sidebar is generated dynamically — owner and recruiter differ.
        $ownerLabels = $sidebar->labels('workspace', $authorizer->permissionsForMembership($ownerMembership));
        $omarLabels = $sidebar->labels('workspace', $authorizer->permissionsForMembership($omarMembership));

        $this->assertContains('Settings', $ownerLabels);
        $this->assertContains('Billing', $ownerLabels);
        $this->assertContains('Jobs', $omarLabels);
        $this->assertNotContains('Settings', $omarLabels);
        $this->assertNotContains('Billing', $omarLabels);
        $this->assertNotSame($ownerLabels, $omarLabels);

        // 13. The System Owner can ALSO use the platform as a normal user (same account).
        $ownerWorkspace = $workspaces->create($ownerId, 'Owner Workspace');
        $this->assertTrue($authorizer->membershipCan($ownerWorkspace['membership_id'], 'job.create'));
    }

    public function test_setup_is_locked_after_install(): void
    {
        $installer = $this->installer();
        $installer->install(['name' => 'Admin', 'email' => 'a@b.co', 'password' => 'password123']);

        $this->expectException(\HaHireAI\Modules\Installer\Application\Exceptions\InstallerException::class);
        $installer->install(['name' => 'Again', 'email' => 'c@d.co', 'password' => 'password123']);
    }

    // --- factories -------------------------------------------------------

    private function installer(): Installer
    {
        return new Installer(
            new MigrationRunner($this->connection, $this->schema),
            new PermissionSeeder(new PermissionRepository($this->connection)),
            $this->registrar(),
            new \HaHireAI\Core\Events\Dispatcher(),
            $this->lockPath,
            dirname(__DIR__, 2) . '/database/migrations',
        );
    }

    private function userRepository(): UserRepository
    {
        return new UserRepository($this->connection);
    }

    private function registrar(): UserRegistrar
    {
        return new UserRegistrar($this->userRepository(), new PasswordHasher());
    }

    private function roleService(): RoleService
    {
        return new RoleService($this->connection, new PermissionRepository($this->connection));
    }

    private function workspaceCreator(): \HaHireAI\Modules\Workspaces\Application\WorkspaceCreator
    {
        return new \HaHireAI\Modules\Workspaces\Application\WorkspaceCreator(
            $this->connection,
            new MembershipService($this->connection),
            $this->roleService(),
        );
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
