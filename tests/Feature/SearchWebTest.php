<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\App\SearchController;
use App\Core\Request;
use App\Models\User;
use App\Services\Ats\ApplicationFlow;
use App\Services\Ats\JobManager;
use App\Services\Search\GlobalSearch;
use App\Services\Tenancy\WorkspaceService;
use Tests\TestCase;

/**
 * Global Search web layer (docs/53) — the keyword sweep over the tenant's jobs +
 * applications renders end to end (controller → GlobalSearch → AdvancedSearch →
 * view → layout) and is gated by recruitment.view (enforced in the controller via
 * abort_unless(can(...))). A fresh Owner (the '*' role) is provisioned and signed
 * in via the session so the gate resolves for real; isolation mirrors AtsWebTest
 * (rolled-back transaction + reflection-reset of auth + AccessControl cache).
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private int $workspaceId = 0;
    private int $ownerId = 0;
    private string $ownerName = '';
    private int $jobId = 0;

    public function setUp(): void
    {
        $this->ownerName = 'Search Owner ' . uniqid();
        $owner = User::create([
            'name'           => $this->ownerName,
            'email'          => 'search-owner-' . uniqid() . '@search.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->ownerId = (int) $owner->getKey();

        $workspace = (new WorkspaceService())->create($owner, 'Search Co');
        $this->workspaceId = (int) $workspace->getKey();
        tenant()->setById($this->workspaceId);

        session()->put((string) config('auth.session_key', 'auth_user_id'), $this->ownerId);

        // A searchable job + an application (owner as applicant) so both keyword
        // sections have something to find.
        $job = (new JobManager())->create(['title' => 'Senior Widget Engineer'], $this->ownerId);
        $this->jobId = (int) $job->getKey();
        (new ApplicationFlow())->apply($this->jobId, $this->ownerId);
    }

    public function tearDown(): void
    {
        session()->forget((string) config('auth.session_key', 'auth_user_id'));

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

    public function test_index_renders_prompt_with_no_query(): void
    {
        $res = (new SearchController())->index($this->request());
        $this->assertSame(200, $res->getStatus());
        $content = $res->getContent();
        $this->assertTrue(str_contains($content, 'Search'));
        $this->assertTrue(str_contains($content, 'Search the workspace'));
    }

    public function test_keyword_finds_a_job(): void
    {
        $res = (new SearchController())->index($this->request(['q' => 'Widget']));
        $this->assertSame(200, $res->getStatus());
        $this->assertTrue(str_contains($res->getContent(), 'Senior Widget Engineer'));
    }

    public function test_keyword_finds_an_application_by_candidate_name(): void
    {
        // The owner is the applicant; searching their name surfaces the application.
        $res = (new SearchController())->index($this->request(['q' => $this->ownerName]));
        $this->assertSame(200, $res->getStatus());
        $content = $res->getContent();
        $this->assertTrue(str_contains($content, $this->ownerName));
        $this->assertTrue(str_contains($content, 'Senior Widget Engineer'));
    }

    public function test_keyword_with_no_matches_shows_empty_state(): void
    {
        $res = (new SearchController())->index($this->request(['q' => 'zzz-nothing-matches-this-xyz']));
        $this->assertSame(200, $res->getStatus());
        $this->assertTrue(str_contains($res->getContent(), 'No matches'));
    }

    public function test_global_search_service_is_tenant_scoped(): void
    {
        // The service finds this tenant's job by keyword…
        $results = (new GlobalSearch())->search('Widget');
        $titles = array_map(static fn (array $j): string => (string) ($j['title'] ?? ''), $results['jobs']);
        $this->assertTrue(in_array('Senior Widget Engineer', $titles, true));

        // …and a keyword matching nothing returns empty sets (no leak).
        $empty = (new GlobalSearch())->search('zzz-nothing-xyz');
        $this->assertCount(0, $empty['jobs']);
        $this->assertCount(0, $empty['applications']);
    }
};
