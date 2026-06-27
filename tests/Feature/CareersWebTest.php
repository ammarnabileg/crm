<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\Public\CareersController;
use App\Core\Request;
use App\Models\User;
use App\Services\Ats\ApplicationFlow;
use App\Services\Ats\JobManager;
use App\Services\Ats\PipelineManager;
use App\Services\Careers\CareersService;
use App\Services\Tenancy\WorkspaceService;
use Tests\TestCase;

/**
 * Public Careers portal (docs/53) — the unauthenticated browse + apply flow.
 *
 * Proves the whole contract end to end and, critically, the isolation: a careers
 * page shows ONLY its own workspace's published jobs (never drafts, never another
 * workspace's jobs), an unknown/suspended workspace 404s, and applying creates a
 * candidate user + an application under the correct workspace, idempotently.
 *
 * Fixtures are built fresh in a rolled-back transaction: workspace A (active) with
 * one published job + one draft job, and a SECOND workspace B (active) with its own
 * published job used to prove cross-tenant isolation. Jobs are tenant-scoped, so we
 * set the active tenant while building each workspace's jobs, then CLEAR it — the
 * public pages must work with no tenant (resolving the workspace from the slug).
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private int $ownerAId = 0;
    private int $wsAId = 0;
    private string $wsASlug = '';
    private string $publishedSlug = '';
    private string $draftSlug = '';

    private int $wsBId = 0;
    private string $wsBSlug = '';
    private string $jobBSlug = '';

    public function setUp(): void
    {
        // --- Workspace A (the company whose careers page we test) -------------
        $ownerA = User::create([
            'name'           => 'Owner A',
            'email'          => 'ownerA-' . uniqid() . '@careers.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->ownerAId = (int) $ownerA->getKey();
        $wsA = (new WorkspaceService())->create($ownerA, 'Acme A ' . uniqid());
        $this->wsAId = (int) $wsA->getKey();
        $this->wsASlug = (string) $wsA->getAttribute('slug');

        tenant()->setById($this->wsAId);
        $jobs = new JobManager();
        $pipelines = new PipelineManager();

        $publishedId = (int) $jobs->create([
            'title'            => 'Senior PHP Engineer',
            'summary'          => 'Build the platform core.',
            'description'      => 'We need a seasoned engineer.',
            'is_remote'        => true,
            'is_salary_public' => true,
            'salary_min'       => 12000,
            'salary_max'       => 18000,
        ], $this->ownerAId)->getKey();
        $pipelines->createDefault($publishedId, $this->ownerAId);
        $jobs->publish($publishedId);
        $this->publishedSlug = (string) app('db')->table('jobs')->where('id', '=', $publishedId)->value('slug');

        // A draft job in the SAME workspace — must never appear publicly.
        $draftId = (int) $jobs->create([
            'title'   => 'Secret Unpublished Role',
            'summary' => 'Not for the public yet.',
        ], $this->ownerAId)->getKey();
        $this->draftSlug = (string) app('db')->table('jobs')->where('id', '=', $draftId)->value('slug');

        // --- Workspace B (a DIFFERENT company; proves isolation) --------------
        $ownerB = User::create([
            'name'           => 'Owner B',
            'email'          => 'ownerB-' . uniqid() . '@careers.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $wsB = (new WorkspaceService())->create($ownerB, 'Beta B ' . uniqid());
        $this->wsBId = (int) $wsB->getKey();
        $this->wsBSlug = (string) $wsB->getAttribute('slug');

        tenant()->setById($this->wsBId);
        $jobBId = (int) $jobs->create([
            'title'   => 'Workspace B Designer',
            'summary' => 'Only on B’s careers page.',
        ], (int) $ownerB->getKey())->getKey();
        $pipelines->createDefault($jobBId, (int) $ownerB->getKey());
        $jobs->publish($jobBId);
        $this->jobBSlug = (string) app('db')->table('jobs')->where('id', '=', $jobBId)->value('slug');

        // The public pages run with NO active tenant.
        tenant()->clear();
    }

    public function tearDown(): void
    {
        tenant()->clear();
    }

    private function request(array $body = [], array $files = []): Request
    {
        $method = $body === [] && $files === [] ? 'GET' : 'POST';
        $req = new Request([], $body, ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/'], [], $files);
        app()->instance('request', $req);

        return $req;
    }

    private function expect404(callable $fn): void
    {
        $threw = false;
        try {
            $fn();
        } catch (\App\Core\Exceptions\HttpException $e) {
            $threw = true;
            $this->assertSame(404, $e->getStatusCode());
        }
        $this->assertTrue($threw, 'Expected a 404 HttpException.');
    }

    public function test_index_lists_only_this_workspace_published_jobs(): void
    {
        $res = (new CareersController())->index($this->request(), $this->wsASlug);
        $this->assertSame(200, $res->getStatus());

        $content = $res->getContent();
        $this->assertTrue(str_contains($content, 'Senior PHP Engineer'), 'Published job is listed.');
        $this->assertFalse(str_contains($content, 'Secret Unpublished Role'), 'Draft job must NOT be listed.');
        $this->assertFalse(str_contains($content, 'Workspace B Designer'), 'Another workspace job must NOT leak.');
    }

    public function test_unknown_workspace_404s(): void
    {
        $this->expect404(fn () => (new CareersController())->index($this->request(), 'no-such-company-xyz'));
    }

    public function test_suspended_workspace_404s(): void
    {
        app('db')->table('workspaces')->where('id', '=', $this->wsAId)
            ->update(['workspace_status_id' => status_id('workspace_statuses', 'suspended')]);

        $this->expect404(fn () => (new CareersController())->index($this->request(), $this->wsASlug));
    }

    public function test_job_detail_renders_apply_form(): void
    {
        $res = (new CareersController())->show($this->request(), $this->wsASlug, $this->publishedSlug);
        $this->assertSame(200, $res->getStatus());

        $content = $res->getContent();
        $this->assertTrue(str_contains($content, 'Senior PHP Engineer'));
        $this->assertTrue(str_contains($content, 'Apply for this role'));
        $this->assertTrue(str_contains($content, 'name="email"'));
    }

    public function test_draft_job_detail_404s(): void
    {
        $this->expect404(fn () => (new CareersController())->show($this->request(), $this->wsASlug, $this->draftSlug));
    }

    public function test_foreign_job_slug_on_this_workspace_404s(): void
    {
        // Workspace B's job slug must NOT resolve under workspace A's careers URL.
        $this->expect404(fn () => (new CareersController())->show($this->request(), $this->wsASlug, $this->jobBSlug));
    }

    public function test_apply_creates_candidate_user_and_application(): void
    {
        $email = 'applicant-' . uniqid() . '@example.com';
        $res = (new CareersController())->apply(
            $this->request(['name' => 'Jane Applicant', 'email' => $email, 'cover_letter' => 'I would love to join.']),
            $this->wsASlug,
            $this->publishedSlug
        );
        $this->assertSame(200, $res->getStatus());
        $this->assertTrue(str_contains($res->getContent(), 'Application received'));

        // A candidate user now exists for that email.
        $userId = (int) app('db')->table('users')->where('email', '=', $email)->value('id');
        $this->assertTrue($userId > 0, 'A candidate user was created.');

        // And an application under workspace A, from the career site, for the job.
        $jobId = (int) app('db')->table('jobs')->where('slug', '=', $this->publishedSlug)->where('workspace_id', '=', $this->wsAId)->value('id');
        $app = app('db')->table('applications')
            ->where('job_id', '=', $jobId)
            ->where('user_id', '=', $userId)
            ->first();
        $this->assertNotNull($app, 'An application row was created.');
        $this->assertSame((int) $this->wsAId, (int) $app['workspace_id'], 'Application is scoped to workspace A.');
        $this->assertSame(lookup_id('application_source', 'career_site'), (int) $app['source_id']);
    }

    public function test_re_applying_is_idempotent(): void
    {
        $email = 'dupe-' . uniqid() . '@example.com';

        $first = (new CareersController())->apply(
            $this->request(['name' => 'Repeat Applicant', 'email' => $email]),
            $this->wsASlug,
            $this->publishedSlug
        );
        $this->assertSame(200, $first->getStatus());
        tenant()->clear();

        $second = (new CareersController())->apply(
            $this->request(['name' => 'Repeat Applicant', 'email' => $email]),
            $this->wsASlug,
            $this->publishedSlug
        );
        $this->assertSame(200, $second->getStatus());
        $this->assertTrue(str_contains($second->getContent(), 'already applied'), 'The second submit is reported as a duplicate.');

        $userId = (int) app('db')->table('users')->where('email', '=', $email)->value('id');
        $jobId = (int) app('db')->table('jobs')->where('slug', '=', $this->publishedSlug)->where('workspace_id', '=', $this->wsAId)->value('id');
        $count = app('db')->table('applications')->where('job_id', '=', $jobId)->where('user_id', '=', $userId)->count();
        $this->assertSame(1, $count, 'Re-applying must not create a second application.');
    }

    public function test_cannot_apply_to_a_draft_job(): void
    {
        $this->expect404(fn () => (new CareersController())->apply(
            $this->request(['name' => 'X', 'email' => 'x-' . uniqid() . '@example.com']),
            $this->wsASlug,
            $this->draftSlug
        ));
    }

    public function test_service_resolves_only_active_workspace(): void
    {
        $service = new CareersService();
        $this->assertNotNull($service->workspaceBySlug($this->wsASlug));
        $this->assertNull($service->workspaceBySlug('definitely-not-a-real-slug'));
    }
};
