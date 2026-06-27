<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\WorkflowVersion;
use App\Services\Workflow\WorkflowBuilder;
use App\Services\Workflow\WorkflowRuntime;
use Tests\TestCase;

/**
 * AI Interview Engine P3 — Workflow Builder + Runtime (docs/51 §8).
 * Covers graph validation, immutable version publishing, and deterministic
 * (model-free) execution: linear walk, await-pause/resume with injected output,
 * and If/Else branching on the run context.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        $workspaceId = (int) app('db')->table('workspaces')->orderBy('id')->value('id');
        tenant()->setById($workspaceId);
    }

    private function builder(): WorkflowBuilder
    {
        return new WorkflowBuilder();
    }

    private function runtime(): WorkflowRuntime
    {
        return WorkflowRuntime::make();
    }

    private function runStatusKey(int $statusId): string
    {
        return (string) app('db')->table('lookup_values')->where('id', '=', $statusId)->value('key');
    }

    // --- Validation ---------------------------------------------------------

    public function test_valid_linear_graph_passes(): void
    {
        $nodes = [
            ['node_key' => 's', 'type' => 'start', 'label' => 'Start'],
            ['node_key' => 'f', 'type' => 'finish', 'label' => 'Finish'],
        ];
        $edges = [['from' => 's', 'to' => 'f']];
        $this->assertSame([], $this->builder()->validate($nodes, $edges));
    }

    public function test_requires_exactly_one_start(): void
    {
        $nodes = [
            ['node_key' => 's1', 'type' => 'start', 'label' => 'A'],
            ['node_key' => 's2', 'type' => 'start', 'label' => 'B'],
            ['node_key' => 'f', 'type' => 'finish', 'label' => 'F'],
        ];
        $edges = [['from' => 's1', 'to' => 'f'], ['from' => 's2', 'to' => 'f']];
        $issues = $this->builder()->validate($nodes, $edges);
        $this->assertTrue($this->hasIssue($issues, 'exactly one'));
    }

    public function test_requires_a_finish(): void
    {
        $nodes = [
            ['node_key' => 's', 'type' => 'start', 'label' => 'S'],
            ['node_key' => 'a', 'type' => 'human_approval', 'label' => 'A'],
        ];
        $edges = [['from' => 's', 'to' => 'a']];
        $issues = $this->builder()->validate($nodes, $edges);
        $this->assertTrue($this->hasIssue($issues, "at least one 'finish'"));
    }

    public function test_unknown_node_type_is_flagged(): void
    {
        $nodes = [
            ['node_key' => 's', 'type' => 'start', 'label' => 'S'],
            ['node_key' => 'x', 'type' => 'teleport', 'label' => 'X'],
            ['node_key' => 'f', 'type' => 'finish', 'label' => 'F'],
        ];
        $edges = [['from' => 's', 'to' => 'x'], ['from' => 'x', 'to' => 'f']];
        $issues = $this->builder()->validate($nodes, $edges);
        $this->assertTrue($this->hasIssue($issues, 'unknown type'));
    }

    public function test_unreachable_node_is_flagged(): void
    {
        $nodes = [
            ['node_key' => 's', 'type' => 'start', 'label' => 'S'],
            ['node_key' => 'f', 'type' => 'finish', 'label' => 'F'],
            ['node_key' => 'orphan', 'type' => 'finish', 'label' => 'O'],
        ];
        $edges = [['from' => 's', 'to' => 'f']];
        $issues = $this->builder()->validate($nodes, $edges);
        $this->assertTrue($this->hasIssue($issues, 'not reachable'));
    }

    public function test_dead_end_non_finish_node_is_flagged(): void
    {
        $nodes = [
            ['node_key' => 's', 'type' => 'start', 'label' => 'S'],
            ['node_key' => 'a', 'type' => 'human_approval', 'label' => 'A'],
            ['node_key' => 'f', 'type' => 'finish', 'label' => 'F'],
        ];
        // 'a' has no outgoing edge.
        $edges = [['from' => 's', 'to' => 'a'], ['from' => 's', 'to' => 'f']];
        $issues = $this->builder()->validate($nodes, $edges);
        $this->assertTrue($this->hasIssue($issues, 'no outgoing edge'));
    }

    // --- Build + publish (DB) ----------------------------------------------

    public function test_create_and_publish_produces_active_version_snapshot(): void
    {
        $wf = $this->builder()->create('Screening', [
            ['node_key' => 's', 'type' => 'start', 'label' => 'Start'],
            ['node_key' => 'a', 'type' => 'human_approval', 'label' => 'Approve'],
            ['node_key' => 'f', 'type' => 'finish', 'label' => 'Finish'],
        ], [
            ['from' => 's', 'to' => 'a'],
            ['from' => 'a', 'to' => 'f'],
        ]);

        $v = $this->builder()->publish((int) $wf->getKey());
        $this->assertSame(1, (int) $v->version);
        $this->assertTrue((bool) $v->is_active);

        $snapshot = $v->snapshot;
        $this->assertSame('s', $snapshot['start']);
        $this->assertSame(3, count($snapshot['nodes']));
        $this->assertSame(2, count($snapshot['edges']));
    }

    public function test_publish_supersedes_prior_version(): void
    {
        $wf = $this->builder()->create('Two Versions', [
            ['node_key' => 's', 'type' => 'start', 'label' => 'S'],
            ['node_key' => 'f', 'type' => 'finish', 'label' => 'F'],
        ], [['from' => 's', 'to' => 'f']]);
        $id = (int) $wf->getKey();

        $this->builder()->publish($id);
        $v2 = $this->builder()->publish($id);
        $this->assertSame(2, (int) $v2->version);

        $active = WorkflowVersion::activeFor($id);
        $this->assertSame(2, (int) $active->version);
        $total = WorkflowVersion::query()->where('workflow_id', '=', $id)->count();
        $this->assertSame(2, $total);
    }

    // --- Runtime (DB) -------------------------------------------------------

    public function test_runtime_pauses_at_await_then_completes_on_resume(): void
    {
        $wf = $this->builder()->create('Approval Flow', [
            ['node_key' => 's', 'type' => 'start', 'label' => 'S'],
            ['node_key' => 'approve', 'type' => 'human_approval', 'label' => 'Approve'],
            ['node_key' => 'done', 'type' => 'finish', 'label' => 'Done'],
        ], [
            ['from' => 's', 'to' => 'approve'],
            ['from' => 'approve', 'to' => 'done'],
        ]);
        $v = $this->builder()->publish((int) $wf->getKey());

        $run = $this->runtime()->start((int) $v->getKey());
        $this->assertSame('awaiting', $this->runStatusKey((int) $run->status_id));
        $this->assertSame('approve', (string) $run->current_node_key);

        $run = $this->runtime()->resume((int) $run->getKey(), ['approved' => true]);
        $this->assertSame('completed', $this->runStatusKey((int) $run->status_id));
        $this->assertSame('done', (string) $run->current_node_key);
        $this->assertSame(3, count($run->steps()));
    }

    public function test_condition_branches_low_score_to_reject(): void
    {
        $v = $this->buildConditionWorkflow();

        $run = $this->runtime()->start((int) $v->getKey(), ['score' => 40]);
        $this->assertSame('completed', $this->runStatusKey((int) $run->status_id));
        $this->assertSame('reject', (string) $run->current_node_key);
    }

    public function test_condition_branches_high_score_to_accept(): void
    {
        $v = $this->buildConditionWorkflow();

        $run = $this->runtime()->start((int) $v->getKey(), ['score' => 80]);
        $this->assertSame('completed', $this->runStatusKey((int) $run->status_id));
        $this->assertSame('accept', (string) $run->current_node_key);
    }

    public function test_await_resume_injects_score_then_branches(): void
    {
        $wf = $this->builder()->create('Ask then Gate', [
            ['node_key' => 's', 'type' => 'start', 'label' => 'S'],
            ['node_key' => 'ask', 'type' => 'ask_question', 'label' => 'Ask'],
            ['node_key' => 'gate', 'type' => 'condition', 'label' => 'Gate'],
            ['node_key' => 'accept', 'type' => 'finish', 'label' => 'Accept'],
            ['node_key' => 'reject', 'type' => 'finish', 'label' => 'Reject'],
        ], [
            ['from' => 's', 'to' => 'ask'],
            ['from' => 'ask', 'to' => 'gate'],
            ['from' => 'gate', 'to' => 'accept', 'condition' => ['field' => 'score', 'op' => '>=', 'value' => 70]],
            ['from' => 'gate', 'to' => 'reject'],
        ]);
        $v = $this->builder()->publish((int) $wf->getKey());

        $run = $this->runtime()->start((int) $v->getKey());
        $this->assertSame('ask', (string) $run->current_node_key);

        // Orchestrator injects the answer's computed score.
        $run = $this->runtime()->resume((int) $run->getKey(), ['answer' => 'Yes'], ['score' => 85]);
        $this->assertSame('completed', $this->runStatusKey((int) $run->status_id));
        $this->assertSame('accept', (string) $run->current_node_key);
    }

    // --- helpers ------------------------------------------------------------

    private function buildConditionWorkflow(): WorkflowVersion
    {
        $wf = $this->builder()->create('Score Gate', [
            ['node_key' => 's', 'type' => 'start', 'label' => 'S'],
            ['node_key' => 'check', 'type' => 'condition', 'label' => 'Check'],
            ['node_key' => 'reject', 'type' => 'finish', 'label' => 'Reject'],
            ['node_key' => 'accept', 'type' => 'finish', 'label' => 'Accept'],
        ], [
            ['from' => 's', 'to' => 'check'],
            ['from' => 'check', 'to' => 'reject', 'condition' => ['field' => 'score', 'op' => '<', 'value' => 60]],
            ['from' => 'check', 'to' => 'accept'],
        ]);

        return $this->builder()->publish((int) $wf->getKey());
    }

    /** @param string[] $issues */
    private function hasIssue(array $issues, string $needle): bool
    {
        foreach ($issues as $issue) {
            if (str_contains($issue, $needle)) {
                return true;
            }
        }

        return false;
    }
};
