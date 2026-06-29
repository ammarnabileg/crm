<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit;

use HaHireAI\Core\Contracts\AccessControl;
use HaHireAI\Core\Contracts\CandidateDirectory;
use HaHireAI\Core\Contracts\InvitationInbox;
use HaHireAI\Core\Contracts\MemberDirectory;
use HaHireAI\Core\Contracts\UserDirectory;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Navigation\Application\ContextSwitcher;
use PHPUnit\Framework\TestCase;

/** The header context switcher model: platform + staff + candidate, one identity. */
final class ContextSwitcherTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function test_collects_platform_staff_and_candidate_contexts(): void
    {
        $_SESSION = ['auth_user_id' => 'u1', 'workspace_id' => 'wsA'];
        $switcher = $this->make(
            staff: [['id' => 'wsA', 'name' => 'Acme'], ['id' => 'wsC', 'name' => 'Globex']],
            candidate: [['id' => 'wsB', 'name' => 'Initech']],
            platform: ['system.dashboard.view'],
            pending: 2,
        );

        $model = $switcher->model('staff');

        $this->assertTrue($model['hasPlatform']);
        $this->assertSame(['Acme', 'Globex'], array_column($model['staff'], 'name'));
        $this->assertSame(['Initech'], array_column($model['candidate'], 'name'));
        $this->assertSame(2, $model['pendingInvites']);
        $this->assertSame('staff', $model['currentType']);
        $this->assertSame('wsA', $model['currentWorkspaceId']);
        $this->assertSame('Acme', $model['currentLabel']); // resolves the active workspace name
    }

    public function test_platform_context_labels_hahireai(): void
    {
        $_SESSION = ['auth_user_id' => 'u1', 'workspace_id' => 'wsA'];
        $model = $this->make(staff: [['id' => 'wsA', 'name' => 'Acme']], candidate: [], platform: ['system.x'])
            ->model('platform');

        $this->assertSame('HaHireAI', $model['currentLabel']);
        $this->assertSame('platform', $model['currentType']);
    }

    public function test_no_platform_access_when_no_system_permissions(): void
    {
        $_SESSION = ['auth_user_id' => 'u1', 'workspace_id' => 'wsB'];
        $model = $this->make(staff: [], candidate: [['id' => 'wsB', 'name' => 'Initech']], platform: [])
            ->model('candidate');

        $this->assertFalse($model['hasPlatform']);
        $this->assertSame([], $model['staff']);
        $this->assertSame('Initech', $model['currentLabel']); // labelled from the candidate pool
    }

    public function test_anonymous_user_gets_an_empty_model(): void
    {
        $_SESSION = [];
        $model = $this->make(staff: [['id' => 'wsA', 'name' => 'Acme']], candidate: [], platform: ['system.x'])
            ->model('staff');

        $this->assertFalse($model['hasPlatform']);
        $this->assertSame([], $model['staff']);
        $this->assertSame('HaHireAI', $model['currentLabel']);
    }

    /**
     * @param  list<array{id:string,name:string}>  $staff
     * @param  list<array{id:string,name:string}>  $candidate
     * @param  list<string>  $platform
     */
    private function make(array $staff, array $candidate, array $platform, int $pending = 0): ContextSwitcher
    {
        $members = new class($staff) implements MemberDirectory {
            /** @param list<array<string,mixed>> $staff */
            public function __construct(private array $staff)
            {
            }

            public function workspacesForUser(string $userId): array
            {
                return $this->staff;
            }

            public function create(string $workspaceId, string $userId, string $status = 'active', ?string $invitedBy = null): string
            {
                return '';
            }

            public function findById(string $membershipId): ?array
            {
                return null;
            }

            public function find(string $workspaceId, string $userId): ?array
            {
                return null;
            }

            public function countForWorkspace(string $workspaceId): int
            {
                return 0;
            }

            public function workspacesForUserDetailed(string $userId): array
            {
                return [];
            }

            public function membersForWorkspace(string $workspaceId): array
            {
                return [];
            }

            public function setStatus(string $workspaceId, string $membershipId, string $status): bool
            {
                return false;
            }

            public function remove(string $workspaceId, string $membershipId): bool
            {
                return false;
            }

            public function touchActivity(string $membershipId): void
            {
            }
        };

        $candidates = new class($candidate) implements CandidateDirectory {
            /** @param list<array<string,mixed>> $candidate */
            public function __construct(private array $candidate)
            {
            }

            public function workspacesForCandidate(string $userId): array
            {
                return $this->candidate;
            }

            public function isCandidate(string $workspaceId, string $userId): bool
            {
                return false;
            }
        };

        $access = new class($platform) implements AccessControl {
            /** @param list<string> $platform */
            public function __construct(private array $platform)
            {
            }

            public function systemPermissionsForUser(string $userId): array
            {
                return $this->platform;
            }

            public function permissionsForMembership(string $membershipId): array
            {
                return [];
            }

            public function membershipCan(string $membershipId, string $permissionKey): bool
            {
                return false;
            }

            public function userIsSystemOwner(string $userId): bool
            {
                return false;
            }
        };

        $users = new class implements UserDirectory {
            public function find(string $id): ?array
            {
                return ['id' => $id, 'name' => 'U', 'email' => 'u1@example.com'];
            }

            public function findByEmail(string $email): ?array
            {
                return null;
            }

            public function emailExists(string $email): bool
            {
                return false;
            }

            public function create(string $name, string $email, string $passwordHash, bool $isSystemOwner = false): string
            {
                return '';
            }

            public function recordLogin(string $id): void
            {
            }

            public function systemOwnerCount(): int
            {
                return 0;
            }

            public function updatePersonal(string $id, string $name, ?string $phone, ?int $yearsExperience, ?int $targetSalary): void
            {
            }
        };

        $invites = new class($pending) implements InvitationInbox {
            public function __construct(private int $pending)
            {
            }

            public function pendingForEmail(string $email): array
            {
                return array_fill(0, $this->pending, [
                    'id' => 'inv', 'workspace_id' => 'w', 'workspace_name' => 'W', 'email' => $email,
                    'role_ids' => [], 'inviter_name' => null, 'created_at' => '', 'expires_at' => null,
                ]);
            }
        };

        return new ContextSwitcher(new AuthContext(new Session(), $users), $members, $candidates, $access, $invites);
    }
}
