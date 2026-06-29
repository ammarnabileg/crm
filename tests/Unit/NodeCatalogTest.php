<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit;

use HaHireAI\Modules\Workflow\Domain\NodeCatalog;
use PHPUnit\Framework\TestCase;

/** The workflow node catalog — the contract shared by the builder and engine. */
final class NodeCatalogTest extends TestCase
{
    public function test_every_node_is_well_formed_and_typed_uniquely(): void
    {
        $nodes = NodeCatalog::nodes();
        $this->assertNotEmpty($nodes);

        $types = [];
        foreach ($nodes as $node) {
            foreach (['type', 'category', 'kind', 'label', 'description', 'icon'] as $key) {
                $this->assertArrayHasKey($key, $node, "node missing {$key}");
                $this->assertNotSame('', (string) $node[$key], "node {$key} empty");
            }
            $this->assertContains($node['category'], NodeCatalog::CATEGORIES, "unknown category {$node['category']}");
            $this->assertContains($node['kind'], ['trigger', 'action', 'condition'], "bad kind for {$node['type']}");
            $types[] = (string) $node['type'];
        }

        $this->assertSame(array_values(array_unique($types)), $types, 'node types must be unique');
    }

    public function test_by_type_index_covers_every_node(): void
    {
        $this->assertCount(count(NodeCatalog::nodes()), NodeCatalog::byType());
        $this->assertArrayHasKey('trigger.candidate_applied', NodeCatalog::byType());
        $this->assertArrayHasKey('recruitment.hire_candidate', NodeCatalog::byType());
    }

    public function test_triggers_bind_to_an_event_or_are_manual_webhook(): void
    {
        foreach (NodeCatalog::nodes() as $node) {
            if ($node['kind'] !== 'trigger') {
                continue;
            }
            $isManualOrWebhook = in_array($node['type'], ['trigger.manual', 'trigger.webhook'], true);
            $this->assertTrue(
                $isManualOrWebhook || ($node['event'] ?? '') !== '',
                "trigger {$node['type']} must bind to an event (or be manual/webhook)",
            );
        }

        // The one event already dispatched in the system must be wired.
        $events = array_column(NodeCatalog::triggerBindings(), 'event');
        $this->assertContains('application.submitted', $events);
    }

    public function test_config_fields_are_ui_renderable_no_code_or_json(): void
    {
        $allowed = ['text', 'select', 'variable'];
        foreach (NodeCatalog::nodes() as $node) {
            foreach ($node['config'] ?? [] as $field) {
                $this->assertArrayHasKey('key', $field);
                $this->assertArrayHasKey('label', $field);
                $this->assertContains($field['type'], $allowed, "node {$node['type']} field {$field['key']} uses unsupported type {$field['type']}");
            }
        }
    }
}
