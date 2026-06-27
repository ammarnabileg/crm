<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\System\CronController;
use App\Core\Model;
use App\Core\Request;
use App\Services\Scheduling\CronRunner;
use App\Services\Scheduling\JobHandlers;
use App\Services\Tenancy\WorkspaceService;
use App\Models\User;
use Tests\TestCase;

/**
 * No-terminal CRON / queue runner (Wave 2b).
 *
 * Proves the orchestrator drains due queued_jobs through the handler registry,
 * routes a poison job to failed_jobs at the attempt ceiling, and ticks due
 * scheduled_tasks (heartbeat + next_run_at advance) — and that the tokenized
 * HTTP trigger fails closed (404 with no token, 403 on a bad token) and only
 * runs on the right token.
 *
 * Fixtures are built fresh inside a rolled-back transaction. A real Owner +
 * workspace are created (and the tenant set) because the controller reads
 * settings('cron.token'), which requires a tenant context. queued_jobs columns
 * available_at/reserved_at/created_at are UNIX ints and every NOT-NULL column
 * (incl. uuid) is populated on insert.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private int $workspaceId = 0;
    private int $ownerId = 0;

    public function setUp(): void
    {
        $owner = User::create([
            'name'           => 'Cron Owner',
            'email'          => 'cron-owner-' . uniqid() . '@cron.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->ownerId = (int) $owner->getKey();

        $workspace = (new WorkspaceService())->create($owner, 'Cron Test Co');
        $this->workspaceId = (int) $workspace->getKey();
        tenant()->setById($this->workspaceId);

        session()->put((string) config('auth.session_key', 'auth_user_id'), $this->ownerId);
    }

    public function tearDown(): void
    {
        // Drop any cron.token we set so it never leaks into another test.
        try {
            settings()->forget('cron.token');
        } catch (\Throwable) {
            // No tenant / nothing stored — nothing to clean.
        }

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

    private function request(array $query = [], array $headers = []): Request
    {
        $server = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/cron/run'];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $req = new Request($query, [], $server, [], []);
        app()->instance('request', $req);

        return $req;
    }

    /**
     * Insert a due queued_jobs row (UNIX-int timestamps) and return its id.
     *
     * @param array<string, mixed> $payload
     */
    private function insertJob(array $payload, int $attempts = 0, ?int $availableAt = null): int
    {
        $now = time();

        return (int) app('db')->table('queued_jobs')->insertGetId([
            'uuid'         => Model::generateUuid(),
            'workspace_id' => $this->workspaceId,
            'queue'        => 'default',
            'payload'      => json_encode($payload),
            'attempts'     => $attempts,
            'reserved_at'  => null,
            'available_at' => $availableAt ?? ($now - 5),
            'created_at'   => $now,
        ]);
    }

    public function test_run_with_empty_queue_is_a_safe_noop(): void
    {
        $summary = (new CronRunner())->run();

        $this->assertArrayHasKey('jobs_processed', $summary);
        $this->assertSame(0, $summary['jobs_processed']);
        $this->assertSame(0, $summary['jobs_failed']);
        $this->assertArrayHasKey('started_at', $summary);
        $this->assertArrayHasKey('finished_at', $summary);
    }

    public function test_due_job_is_processed_by_its_handler_and_deleted(): void
    {
        $ran = (object) ['count' => 0, 'data' => null];

        $handlers = (new JobHandlers())->register('test.record', function (array $data) use ($ran): void {
            $ran->count++;
            $ran->data = $data;
        });

        $jobId = $this->insertJob(['type' => 'test.record', 'data' => ['foo' => 'bar']]);

        $summary = (new CronRunner($handlers))->run();

        $this->assertSame(1, $ran->count);
        $this->assertSame(['foo' => 'bar'], $ran->data);
        $this->assertSame(1, $summary['jobs_processed']);
        // The processed row is gone.
        $this->assertFalse(
            app('db')->table('queued_jobs')->where('id', '=', $jobId)->exists()
        );
    }

    public function test_failing_job_lands_in_failed_jobs_at_the_attempt_ceiling(): void
    {
        $handlers = (new JobHandlers())->register('test.boom', function (): void {
            throw new \RuntimeException('kaboom');
        });

        // Preset attempts to one below the ceiling so a single run() moves it to
        // failed_jobs (the runner bumps it to 3 = MAX_ATTEMPTS).
        $jobId = $this->insertJob(['type' => 'test.boom', 'data' => []], 2);

        $summary = (new CronRunner($handlers))->run();

        $this->assertSame(1, $summary['jobs_failed']);
        $this->assertSame(0, $summary['jobs_processed']);
        // Removed from the queue ...
        $this->assertFalse(
            app('db')->table('queued_jobs')->where('id', '=', $jobId)->exists()
        );
        // ... and recorded in failed_jobs with the exception captured.
        $failed = app('db')->table('failed_jobs')
            ->where('workspace_id', '=', $this->workspaceId)
            ->orderBy('id', 'desc')
            ->first();
        $this->assertNotNull($failed);
        $this->assertTrue(str_contains((string) $failed['exception'], 'kaboom'));
    }

    public function test_failing_job_below_ceiling_is_retried_with_backoff(): void
    {
        $handlers = (new JobHandlers())->register('test.boom', function (): void {
            throw new \RuntimeException('retry me');
        });

        $jobId = $this->insertJob(['type' => 'test.boom', 'data' => []], 0);

        $summary = (new CronRunner($handlers))->run();

        // Not failed yet — released for a later attempt.
        $this->assertSame(0, $summary['jobs_failed']);
        $row = app('db')->table('queued_jobs')->where('id', '=', $jobId)->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row['attempts']);
        $this->assertNull($row['reserved_at']);
        // available_at pushed into the future by the backoff.
        $this->assertTrue((int) $row['available_at'] > time());
    }

    public function test_due_scheduled_task_is_ticked_and_advanced(): void
    {
        $taskId = (int) app('db')->table('scheduled_tasks')->insertGetId([
            'uuid'        => Model::generateUuid(),
            'name'        => 'Heartbeat ' . uniqid(),
            'cron'        => '@hourly',
            'is_active'   => 1,
            'last_run_at' => null,
            'next_run_at' => null,
            'last_status' => null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        (new CronRunner())->run();

        $row = app('db')->table('scheduled_tasks')->where('id', '=', $taskId)->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row['last_run_at']);
        $this->assertSame('ran', $row['last_status']);
        // next_run_at is now set and lies in the future (>= now).
        $this->assertNotNull($row['next_run_at']);
        $this->assertTrue(strtotime((string) $row['next_run_at']) >= strtotime(now()));
    }

    public function test_controller_returns_404_when_no_token_configured(): void
    {
        // No cron.token setting and no CRON_TOKEN env => disabled.
        $res = (new CronController())->run($this->request());
        $this->assertSame(404, $res->getStatus());
    }

    public function test_controller_returns_403_on_bad_token(): void
    {
        settings()->set('cron.token', 'secret');

        $res = (new CronController())->run($this->request(['token' => 'wrong']));
        $this->assertSame(403, $res->getStatus());
    }

    public function test_controller_runs_on_correct_token_via_query(): void
    {
        settings()->set('cron.token', 'secret');

        $res = (new CronController())->run($this->request(['token' => 'secret']));
        $this->assertSame(200, $res->getStatus());

        $body = json_decode($res->getContent(), true);
        $this->assertSame('ok', $body['status']);
        $this->assertArrayHasKey('jobs_processed', $body);
        $this->assertArrayHasKey('scheduled_ticked', $body);
    }

    public function test_controller_accepts_token_via_header(): void
    {
        settings()->set('cron.token', 'secret');

        $res = (new CronController())->run($this->request([], ['X-Cron-Token' => 'secret']));
        $this->assertSame(200, $res->getStatus());
    }
};
