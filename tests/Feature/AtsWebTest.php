<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\Ats\ApplicationController;
use App\Controllers\Ats\BoardController;
use App\Controllers\Ats\JobController;
use App\Controllers\Ats\RecruiterDashboardController;
use App\Core\Request;
use App\Services\Ats\ApplicationFlow;
use App\Services\Ats\JobManager;
use App\Services\Ats\PipelineManager;
use Tests\TestCase;

/**
 * ATS web layer (docs/53) — the recruiter workspace, jobs list, Kanban board and
 * application detail render end to end (controller → service → view → layout) with
 * real data. Routes attach permission:recruitment.view / recruitment.manage
 * (the generic RequirePermission gate already in use by the system routes).
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private int $jobId = 0;
    private int $appId = 0;

    public function setUp(): void
    {
        $db = app('db');
        tenant()->setById((int) $db->table('workspaces')->orderBy('id')->value('id'));
        $userId = (int) $db->table('users')->orderBy('id')->value('id');

        $job = (new JobManager())->create(['title' => 'UI Smoke Job'], $userId);
        $this->jobId = (int) $job->getKey();
        (new PipelineManager())->createDefault($this->jobId, $userId);
        $this->appId = (int) (new ApplicationFlow())->apply($this->jobId, $userId)->getKey();
    }

    /**
     * Rendering touches auth() (the AuthManager resolves the user once and caches
     * "resolved"). Reset that resolution + the AccessControl per-request cache on
     * the EXISTING singletons (preserving registered policy gates) so later
     * session-auth tests re-resolve cleanly — test isolation, no leak.
     */
    public function tearDown(): void
    {
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

    private function request(array $query = []): Request
    {
        $req = new Request($query, [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'], [], []);
        app()->instance('request', $req); // the layout calls request()->path()

        return $req;
    }

    public function test_recruiter_dashboard_renders(): void
    {
        $res = (new RecruiterDashboardController())->index($this->request());
        $this->assertSame(200, $res->getStatus());
        $this->assertTrue(str_contains($res->getContent(), 'Recruiter workspace'));
    }

    public function test_jobs_page_renders_with_the_job(): void
    {
        $res = (new JobController())->index($this->request());
        $this->assertSame(200, $res->getStatus());
        $this->assertTrue(str_contains($res->getContent(), 'UI Smoke Job'));
    }

    public function test_board_renders_with_stages(): void
    {
        $res = (new BoardController())->show($this->request(['job' => $this->jobId]));
        $this->assertSame(200, $res->getStatus());
        $content = $res->getContent();
        $this->assertTrue(str_contains($content, 'Applied'));   // initial stage column
        $this->assertTrue(str_contains($content, 'Pipeline'));
    }

    public function test_application_detail_renders_timeline(): void
    {
        $res = (new ApplicationController())->show($this->request(['id' => $this->appId]));
        $this->assertSame(200, $res->getStatus());
        $this->assertTrue(str_contains($res->getContent(), 'Timeline'));
    }
};
