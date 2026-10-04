<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit;

use HaHireAI\Modules\Workflow\Domain\WorkflowGraph;
use PHPUnit\Framework\TestCase;

/** The pure graph→steps compiler, summary and validator behind the visual builder. */
final class WorkflowGraphTest extends TestCase
{
    public function test_compiles_a_linear_graph_into_ordered_steps(): void
    {
        $graph = [
            'nodes' => [
                ['id' => 't', 'type' => 'trigger.candidate_applied', 'config' => []],
                ['id' => 'a', 'type' => 'ai.summary', 'config' => ['subject' => 'candidate']],
                ['id' => 'b', 'type' => 'workspace.create_task', 'config' => ['title' => 'Review']],
            ],
            'edges' => [
                ['from' => 't', 'to' => 'a'],
                ['from' => 'a', 'to' => 'b'],
            ],
        ];

        $steps = WorkflowGraph::compile($graph);

        $this->assertCount(2, $steps); // the trigger is not a step
        $this->assertSame('ai.summary', $steps[0]['action']);
        $this->assertSame('workspace.create_task', $steps[1]['action']);
        $this->assertSame('Review', $steps[1]['params']['title']);
    }

    public function test_condition_node_attaches_its_test_to_following_steps(): void
    {
        $graph = [
            'nodes' => [
                ['id' => 't', 'type' => 'trigger.candidate_applied', 'config' => []],
                ['id' => 'c', 'type' => 'condition.if', 'config' => ['field' => 'source', 'op' => 'equals', 'value' => 'referral']],
                ['id' => 'a', 'type' => 'workspace.create_task', 'config' => ['title' => 'Fast-track']],
            ],
            'edges' => [
                ['from' => 't', 'to' => 'c'],
                ['from' => 'c', 'to' => 'a', 'branch' => 'true'],
            ],
        ];

        $steps = WorkflowGraph::compile($graph);

        $this->assertCount(1, $steps);
        $this->assertSame('workspace.create_task', $steps[0]['action']);
        $this->assertArrayHasKey('condition', $steps[0]);
        $this->assertSame('source', $steps[0]['condition']['field']);
        $this->assertSame('referral', $steps[0]['condition']['value']);
    }

    public function test_summarize_reads_naturally(): void
    {
        $graph = [
            'nodes' => [
                ['id' => 't', 'type' => 'trigger.candidate_applied', 'config' => []],
                ['id' => 'a', 'type' => 'ai.summary', 'config' => ['subject' => 'x']],
                ['id' => 'b', 'type' => 'workspace.create_task', 'config' => ['title' => 'x']],
            ],
            'edges' => [['from' => 't', 'to' => 'a'], ['from' => 'a', 'to' => 'b']],
        ];

        $summary = WorkflowGraph::summarize($graph);

        $this->assertStringContainsString('When candidate applied', $summary);
        $this->assertStringContainsString('generate summary', $summary);
        $this->assertStringContainsString('create task', $summary);
    }

    public function test_validate_flags_missing_trigger_and_actions(): void
    {
        $empty = WorkflowGraph::validate(['nodes' => [], 'edges' => []]);
        $this->assertFalse($empty['ok']);
        $this->assertNotEmpty($empty['issues']);

        $good = WorkflowGraph::validate([
            'nodes' => [
                ['id' => 't', 'type' => 'trigger.candidate_applied', 'config' => []],
                ['id' => 'a', 'type' => 'util.log', 'config' => ['message' => 'hi']],
            ],
            'edges' => [['from' => 't', 'to' => 'a']],
        ]);
        $this->assertTrue($good['ok'], json_encode($good));
    }

    public function test_validate_flags_a_required_field_left_empty(): void
    {
        $result = WorkflowGraph::validate([
            'nodes' => [
                ['id' => 't', 'type' => 'trigger.candidate_applied', 'config' => []],
                ['id' => 'a', 'type' => 'workspace.create_task', 'config' => ['title' => '']], // required title empty
            ],
            'edges' => [['from' => 't', 'to' => 'a']],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('a', $result['node_issues']);
    }
}
