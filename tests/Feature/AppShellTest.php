<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\App\DashboardController;
use App\Core\Request;
use App\Models\User;
use App\Services\Tenancy\WorkspaceService;
use Tests\TestCase;

/**
 * The authenticated application shell (resources/views/layouts/app.php) ships its
 * cross-cutting Ui affordances on every signed-in page: the no-flash theme script,
 * the dark-mode toggle, the toast mount region and the dark colour variants. This
 * used to be asserted against the /design catalog; that internal page was removed,
 * so the shell is now proven through a real authenticated page (the dashboard)
 * rendered end to end through the layout — exactly as a user sees it.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private int $ownerId = 0;
    private int $workspaceId = 0;

    public function setUp(): void
    {
        $owner = User::create([
            'name'           => 'Shell Owner',
            'email'          => 'shell-' . uniqid() . '@shell.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->ownerId = (int) $owner->getKey();
        $this->workspaceId = (int) (new WorkspaceService())->create($owner, 'Shell Co')->getKey();

        tenant()->setById($this->workspaceId);
        session()->put((string) config('auth.session_key', 'auth_user_id'), $this->ownerId);
    }

    public function tearDown(): void
    {
        session()->forget((string) config('auth.session_key', 'auth_user_id'));
        tenant()->clear();

        $auth = app('auth');
        $r = new \ReflectionObject($auth);
        foreach (['resolved' => false, 'user' => null] as $prop => $value) {
            if ($r->hasProperty($prop)) {
                $p = $r->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue($auth, $value);
            }
        }

        $access = app('access');
        $ra = new \ReflectionObject($access);
        if ($ra->hasProperty('cache')) {
            $p = $ra->getProperty('cache');
            $p->setAccessible(true);
            $p->setValue($access, []);
        }
    }

    private function request(): Request
    {
        $req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard'], [], []);
        app()->instance('request', $req); // the layout calls request()->path()

        return $req;
    }

    public function test_authenticated_shell_ships_dark_mode_and_toast_region(): void
    {
        $content = (new DashboardController())->index($this->request())->getContent();

        $this->assertTrue(str_contains($content, 'data-theme-toggle'), 'dark-mode toggle present');
        $this->assertTrue(str_contains($content, 'halaops-theme'), 'no-flash theme script present');
        $this->assertTrue(str_contains($content, 'id="toast-region"'), 'toast mount region present');
        $this->assertTrue(str_contains($content, 'dark:bg-slate-900'), 'dark shell variants present');
    }

    public function test_sidebar_is_grouped_and_hides_platform_from_non_super_admin(): void
    {
        // Phase 18: ONE grouped, permission-driven sidebar.
        $content = (new DashboardController())->index($this->request())->getContent();

        // Module group headings render (the Owner can see Recruitment + Workspace).
        $this->assertTrue(str_contains($content, '>Recruitment</p>'), 'Recruitment group heading present');
        $this->assertTrue(str_contains($content, '>Workspace</p>'), 'Workspace group heading present');
        // The unified module replaced the old "Recruiter Workspace" silo.
        $this->assertFalse(str_contains($content, 'Recruiter Workspace'), 'old "Recruiter Workspace" label is gone');
        // Platform ops are super-admin only — hidden from a normal Owner.
        $this->assertFalse(str_contains($content, '>Platform</p>'), 'Platform group hidden from non-super-admin');
        $this->assertFalse(str_contains($content, 'All Workspaces'), 'platform items hidden from non-super-admin');
    }
};
