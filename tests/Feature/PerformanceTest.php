<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\Ats\BoardController;
use App\Controllers\Ats\JobController;
use App\Core\Request;
use App\Services\Ats\ApplicationFlow;
use App\Services\Ats\JobManager;
use App\Services\Ats\PipelineManager;
use Tests\TestCase;

/**
 * Performance & Scalability regressions (Phase 12). The query counter proves the
 * hot list pages issue a CONSTANT number of queries regardless of row count (no
 * N+1), and the schema keeps the composite indexes those pages rely on. These fail
 * loudly if a later change reintroduces a per-row query or drops a hot index.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        $db = app('db');
        tenant()->setById((int) $db->table('workspaces')->orderBy('id')->value('id'));
    }

    public function tearDown(): void
    {
        // Reset the resolved auth user + RBAC cache on the existing singletons so
        // later session-auth tests re-resolve cleanly (same isolation as AtsWebTest).
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
        app()->instance('request', $req);

        return $req;
    }

    public function test_query_counter_increments(): void
    {
        $db = app('db');
        $db->resetQueryCount();
        $db->table('workspaces')->limit(1)->get();
        $this->assertTrue($db->getQueryCount() >= 1);
    }

    public function test_jobs_index_has_no_n_plus_1(): void
    {
        $db = app('db');
        $userId = (int) $db->table('users')->orderBy('id')->value('id');
        $jobs = new JobManager();
        $ctrl = new JobController();

        // Warm per-request caches (auth resolution, RBAC) so they don't skew the
        // comparison below.
        $jobs->create(['title' => 'Warm'], $userId);
        $ctrl->index($this->request());

        // Batch A: a couple of jobs.
        $jobs->create(['title' => 'A1'], $userId);
        $jobs->create(['title' => 'A2'], $userId);
        $db->resetQueryCount();
        $ctrl->index($this->request());
        $qA = $db->getQueryCount();

        // Batch B: six MORE jobs. With the grouped-count fix the query count must
        // NOT grow with the number of jobs; an N+1 would add ~6 queries.
        foreach (range(1, 6) as $i) {
            $jobs->create(['title' => 'B' . $i], $userId);
        }
        $db->resetQueryCount();
        $ctrl->index($this->request());
        $qB = $db->getQueryCount();

        $this->assertTrue(
            $qB <= $qA + 1,
            "Jobs index looks N+1: {$qA} queries for 3 jobs vs {$qB} for 9 jobs (should be constant)."
        );
    }

    public function test_board_load_is_bounded(): void
    {
        $db = app('db');
        $userId = (int) $db->table('users')->orderBy('id')->value('id');
        $job = (new JobManager())->create(['title' => 'Board Perf'], $userId);
        $jobId = (int) $job->getKey();
        (new PipelineManager())->createDefault($jobId, $userId);

        $flow = new ApplicationFlow();
        $userIds = array_map(static fn ($r): int => (int) $r['id'], $db->table('users')->orderBy('id')->limit(4)->get());
        foreach ($userIds as $uid) {
            $flow->apply($jobId, $uid);
        }

        // Warm caches, then measure: the board must load all applications in a
        // bounded number of queries (one stages query + one applications query +
        // fixed view overhead) — never one-per-application.
        $board = new BoardController();
        $board->show($this->request(['job' => $jobId]));
        $db->resetQueryCount();
        $board->show($this->request(['job' => $jobId]));
        $q = $db->getQueryCount();

        $this->assertTrue($q < 15, "Board issued {$q} queries — expected a small constant (no per-application N+1).");
    }

    public function test_hot_tables_keep_their_composite_indexes(): void
    {
        $db = app('db');
        $hasIndexOn = static function (string $table, string $firstCol) use ($db): bool {
            foreach ($db->select("SHOW INDEX FROM `{$table}`") as $row) {
                if ((int) $row['Seq_in_index'] === 1 && $row['Column_name'] === $firstCol) {
                    return true;
                }
            }
            return false;
        };

        // Tenant-scoped hot paths must lead their indexes with workspace_id.
        $this->assertTrue($hasIndexOn('applications', 'workspace_id'), 'applications needs a workspace_id-leading index');
        $this->assertTrue($hasIndexOn('jobs', 'workspace_id'), 'jobs needs a workspace_id-leading index');
        $this->assertTrue($hasIndexOn('status_histories', 'workspace_id'), 'status_histories needs a workspace_id-leading index');
        // RBAC resolution joins (run on every authorized request).
        $this->assertTrue($hasIndexOn('role_permissions', 'role_id'), 'role_permissions needs a role_id-leading index');
        $this->assertTrue($hasIndexOn('membership_roles', 'membership_id'), 'membership_roles needs a membership_id-leading index');
    }
};
