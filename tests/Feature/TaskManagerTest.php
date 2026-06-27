<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Task;
use App\Services\Ats\TaskManager;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Task Manager service (docs/53 ATS Task Management). Covers creation with status /
 * priority validation, completion stamping, (re)assignment, and the relation /
 * assignee / open listings. All rows built in-tx and rolled back.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        tenant()->setById((int) app('db')->table('workspaces')->orderBy('id')->value('id'));
    }

    private function service(): TaskManager
    {
        return new TaskManager();
    }

    private function userId(): int
    {
        return (int) app('db')->table('users')->orderBy('id')->value('id');
    }

    public function test_create_persists_task_with_tenant_and_defaults(): void
    {
        $task = $this->service()->create([
            'title'      => 'Call candidate',
            'created_by' => $this->userId(),
        ]);

        $this->assertInstanceOf(Task::class, $task);
        $this->assertNotNull($task->getKey());
        $this->assertSame('open', (string) $task->status);
        $this->assertSame('normal', (string) $task->priority);
        $this->assertSame((int) tenant()->id(), (int) $task->workspace_id);
    }

    public function test_create_relates_to_subject(): void
    {
        $task = $this->service()->create([
            'title'        => 'Review resume',
            'related_type' => 'App\\Models\\Application',
            'related_id'   => 123,
        ]);

        $this->assertSame('App\\Models\\Application', (string) $task->related_type);
        $this->assertSame(123, (int) $task->related_id);
    }

    public function test_create_rejects_invalid_status(): void
    {
        $threw = false;
        try {
            $this->service()->create(['title' => 'X', 'status' => 'bogus']);
        } catch (InvalidArgumentException) {
            $threw = true;
        }
        $this->assertTrue($threw);
    }

    public function test_create_rejects_invalid_priority(): void
    {
        $threw = false;
        try {
            $this->service()->create(['title' => 'X', 'priority' => 'sky_high']);
        } catch (InvalidArgumentException) {
            $threw = true;
        }
        $this->assertTrue($threw);
    }

    public function test_create_rejects_empty_title(): void
    {
        $threw = false;
        try {
            $this->service()->create(['title' => '   ']);
        } catch (InvalidArgumentException) {
            $threw = true;
        }
        $this->assertTrue($threw);
    }

    public function test_complete_sets_status_and_timestamp(): void
    {
        $svc = $this->service();
        $task = $svc->create(['title' => 'Finish me']);

        $done = $svc->complete((int) $task->getKey());

        $this->assertNotNull($done);
        $this->assertSame('done', (string) $done->status);
        $this->assertNotNull($done->completed_at);

        $fresh = Task::find((int) $task->getKey());
        $this->assertSame('done', (string) $fresh->status);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_complete_unknown_id_returns_null(): void
    {
        $this->assertNull($this->service()->complete(999999999));
    }

    public function test_assign_sets_assignee(): void
    {
        $svc = $this->service();
        $task = $svc->create(['title' => 'Assign me']);

        $assigned = $svc->assign((int) $task->getKey(), $this->userId());

        $this->assertNotNull($assigned);
        $this->assertSame($this->userId(), (int) $assigned->assignee_id);
    }

    public function test_for_related_filters_by_subject(): void
    {
        $svc = $this->service();
        $svc->create(['title' => 'A', 'related_type' => 'App\\Models\\Job', 'related_id' => 7]);
        $svc->create(['title' => 'B', 'related_type' => 'App\\Models\\Job', 'related_id' => 7]);
        $svc->create(['title' => 'C', 'related_type' => 'App\\Models\\Job', 'related_id' => 8]);

        $this->assertSame(2, count($svc->forRelated('App\\Models\\Job', 7)));
        $this->assertSame(1, count($svc->forRelated('App\\Models\\Job', 8)));
    }

    public function test_for_assignee_filters_by_user(): void
    {
        $svc = $this->service();
        $uid = $this->userId();
        $svc->create(['title' => 'Mine', 'assignee_id' => $uid]);
        $svc->create(['title' => 'Unassigned']);

        $mine = $svc->forAssignee($uid);
        $this->assertTrue(count($mine) >= 1);
        foreach ($mine as $t) {
            $this->assertSame($uid, (int) $t->assignee_id);
        }
    }

    public function test_open_excludes_done_and_canceled(): void
    {
        $svc = $this->service();
        $open = $svc->create(['title' => 'Open one']);
        $svc->create(['title' => 'In progress', 'status' => 'in_progress']);
        $doneTask = $svc->create(['title' => 'Done one']);
        $svc->complete((int) $doneTask->getKey());
        $svc->create(['title' => 'Canceled one', 'status' => 'canceled']);

        $statuses = array_map(static fn (Task $t): string => (string) $t->status, $svc->open());

        $this->assertTrue(in_array('open', $statuses, true));
        $this->assertTrue(in_array('in_progress', $statuses, true));
        $this->assertFalse(in_array('done', $statuses, true));
        $this->assertFalse(in_array('canceled', $statuses, true));
    }
};
