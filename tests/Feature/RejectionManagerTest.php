<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Model;
use App\Models\Application;
use App\Services\Ats\RejectionManager;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Rejection Manager service (docs/53 ATS Rejection Management). Builds an
 * application in-tx, rejects it, and asserts the status flip + decided_at, the
 * status_histories audit row, the reason note, and reason/validation behaviour.
 * All rolled back. (The candidate.rejected event is dispatched best-effort and is
 * not asserted here — the automation layer is exercised elsewhere.)
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private int $userId = 0;

    public function setUp(): void
    {
        tenant()->setById((int) app('db')->table('workspaces')->orderBy('id')->value('id'));
    }

    private function service(): RejectionManager
    {
        return new RejectionManager();
    }

    /** Build job + application in 'applied' status; return the application id. */
    private function makeApplication(): int
    {
        $db = app('db');
        $workspaceId = (int) tenant()->id();
        $this->userId = (int) $db->table('users')->orderBy('id')->value('id');
        $now = now();

        $jobId = $db->table('jobs')->insertGetId([
            'uuid'          => Model::generateUuid(),
            'workspace_id'  => $workspaceId,
            'job_status_id' => status_id('job_statuses', 'open'),
            'title'         => 'Rejection Test Job',
            'slug'          => 'rej-' . substr(Model::generateUuid(), 0, 12),
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        return $db->table('applications')->insertGetId([
            'uuid'                  => Model::generateUuid(),
            'workspace_id'          => $workspaceId,
            'job_id'                => $jobId,
            'user_id'               => $this->userId,
            'application_status_id' => status_id('application_statuses', 'applied'),
            'applied_at'            => $now,
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);
    }

    public function test_reject_sets_status_and_decided_at(): void
    {
        $appId = $this->makeApplication();
        $this->service()->reject($appId, 'not_qualified', null, $this->userId);

        $app = Application::find($appId);
        $this->assertSame(status_id('application_statuses', 'rejected'), (int) $app->application_status_id);
        $this->assertSame('rejected', $app->statusKey());
        $this->assertNotNull($app->decided_at);
    }

    public function test_reject_writes_status_history_row(): void
    {
        $appId = $this->makeApplication();
        $this->service()->reject($appId, 'position_filled', null, $this->userId);

        $row = app('db')->table('status_histories')
            ->where('workspace_id', '=', (int) tenant()->id())
            ->where('subject_type', '=', 'App\\Models\\Application')
            ->where('subject_id', '=', $appId)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('rejected', (string) $row['to_status_key']);
        $this->assertSame('applied', (string) $row['from_status_key']);
        $this->assertSame('application', (string) $row['status_type']);
        $this->assertSame(status_id('application_statuses', 'rejected'), (int) $row['to_status_id']);
    }

    public function test_reject_adds_reason_note(): void
    {
        $appId = $this->makeApplication();
        $this->service()->reject($appId, 'salary_mismatch', 'standard_rejection', $this->userId);

        $note = app('db')->table('notes')
            ->where('workspace_id', '=', (int) tenant()->id())
            ->where('notable_type', '=', 'App\\Models\\Application')
            ->where('notable_id', '=', $appId)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($note);
        $this->assertTrue(str_contains((string) $note['body'], 'salary_mismatch'));
        $this->assertTrue(str_contains((string) $note['body'], 'standard_rejection'));
    }

    public function test_reject_unknown_reason_throws_and_leaves_status(): void
    {
        $appId = $this->makeApplication();

        $threw = false;
        try {
            $this->service()->reject($appId, 'made_up_reason');
        } catch (InvalidArgumentException) {
            $threw = true;
        }
        $this->assertTrue($threw);

        // Status untouched.
        $this->assertSame('applied', Application::find($appId)->statusKey());
    }

    public function test_reject_unknown_application_throws(): void
    {
        $threw = false;
        try {
            $this->service()->reject(999999999, 'other');
        } catch (RuntimeException) {
            $threw = true;
        }
        $this->assertTrue($threw);
    }

    public function test_reasons_returns_expected_keys(): void
    {
        $reasons = $this->service()->reasons();

        foreach (['not_qualified', 'position_filled', 'salary_mismatch', 'failed_assessment', 'withdrew', 'other'] as $key) {
            $this->assertTrue(array_key_exists($key, $reasons));
        }
    }
};
