<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit;

use HaHireAI\Modules\Workflow\Domain\NodeCatalog;
use HaHireAI\Modules\Workflow\Domain\WorkflowGraph;
use HaHireAI\Modules\Workflow\Domain\WorkflowTemplates;
use PHPUnit\Framework\TestCase;

/** Every ready-made template must be a valid, usable workflow graph. */
final class WorkflowTemplatesTest extends TestCase
{
    public function test_there_are_at_least_ten_templates_with_unique_keys(): void
    {
        $all = WorkflowTemplates::all();
        $this->assertGreaterThanOrEqual(10, count($all));

        $keys = array_column($all, 'key');
        $this->assertSame(count($keys), count(array_unique($keys)), 'template keys must be unique');
    }

    public function test_every_template_is_a_valid_workflow_and_uses_real_node_types(): void
    {
        $known = array_keys(NodeCatalog::byType());

        foreach (WorkflowTemplates::all() as $tpl) {
            $graph = ['nodes' => $tpl['nodes'], 'edges' => $tpl['edges']];

            // Every node type exists in the catalog (no typos / dangling nodes).
            foreach ($tpl['nodes'] as $node) {
                $this->assertContains($node['type'], $known, "template {$tpl['key']} uses unknown node {$node['type']}");
            }

            // Compiles to at least one executable step and validates clean.
            $steps = WorkflowGraph::compile($graph);
            $this->assertNotEmpty($steps, "template {$tpl['key']} compiled to no steps");

            $v = WorkflowGraph::validate($graph);
            $this->assertTrue($v['ok'], "template {$tpl['key']} is invalid: " . json_encode($v['issues']));
        }
    }

    public function test_find_returns_a_template_by_key(): void
    {
        $this->assertNotNull(WorkflowTemplates::find('ai-screen'));
        $this->assertNull(WorkflowTemplates::find('does-not-exist'));
    }
}
