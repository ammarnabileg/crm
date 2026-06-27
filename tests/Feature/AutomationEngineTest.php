<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AutomationVersion;
use App\Services\Automation\AutomationBuilder;
use App\Services\Automation\AutomationEngine;
use RuntimeException;
use Tests\TestCase;

/**
 * Workflow Automation Engine (docs/51) — trigger → conditions → actions, with
 * execution logging, versioning/rollback, and registry extensibility. Exercised
 * with built-in conditions/actions (deterministic, no network).
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        $workspaceId = (int) app('db')->table('workspaces')->orderBy('id')->value('id');
        tenant()->setById($workspaceId);
    }

    private function builder(): AutomationBuilder
    {
        return new AutomationBuilder();
    }

    private function engine(): AutomationEngine
    {
        return AutomationEngine::make();
    }

    public function test_create_rejects_unknown_trigger(): void
    {
        $threw = false;
        try {
            $this->builder()->create('Bad', 'not.a.real.trigger', [['type' => 'action', 'key' => 'log']]);
        } catch (RuntimeException) {
            $threw = true;
        }
        $this->assertTrue($threw);
    }

    public function test_create_persists_automation_and_steps(): void
    {
        $a = $this->builder()->create('Welcome', 'user.registered', [
            ['type' => 'condition', 'key' => 'always'],
            ['type' => 'action', 'key' => 'log', 'config' => ['message' => 'hi']],
        ]);

        $this->assertSame('user.registered', (string) $a->trigger_event);
        $this->assertSame(2, count($a->steps()));
    }

    public function test_trigger_runs_matching_automation_and_records(): void
    {
        $this->builder()->create('On Passed', 'candidate.passed', [
            ['type' => 'condition', 'key' => 'always'],
            ['type' => 'action', 'key' => 'log', 'config' => ['message' => 'passed!']],
        ]);

        $runs = $this->engine()->trigger('candidate.passed', ['candidate_id' => 7]);
        $this->assertSame(1, count($runs));
        $this->assertSame('completed', (string) $runs[0]->status);
        $this->assertSame(2, count($runs[0]->steps()));
    }

    public function test_conditions_gate_actions(): void
    {
        $a = $this->builder()->create('Gate', 'application.submitted', [
            ['type' => 'condition', 'key' => 'expression', 'config' => ['field' => 'score', 'op' => '>', 'value' => 85]],
            ['type' => 'action', 'key' => 'log'],
        ]);
        $id = (int) $a->getKey();

        $low = $this->engine()->run($id, ['score' => 50]);
        $this->assertSame('skipped', (string) $low->status);
        $this->assertSame(1, count($low->steps())); // only the condition ran

        $high = $this->engine()->run($id, ['score' => 90]);
        $this->assertSame('completed', (string) $high->status);
        $this->assertSame(2, count($high->steps()));
    }

    public function test_action_output_chains_into_later_condition(): void
    {
        $a = $this->builder()->create('Chain', 'interview.completed', [
            ['type' => 'action', 'key' => 'set_context', 'config' => ['values' => ['score' => 90]]],
            ['type' => 'condition', 'key' => 'expression', 'config' => ['field' => 'score', 'op' => '>=', 'value' => 80]],
            ['type' => 'action', 'key' => 'log', 'config' => ['message' => 'qualified']],
        ]);

        $run = $this->engine()->run((int) $a->getKey(), []);
        $this->assertSame('completed', (string) $run->status);
        $this->assertSame(3, count($run->steps()));
    }

    public function test_end_action_stops_run(): void
    {
        $a = $this->builder()->create('Stopper', 'interview.completed', [
            ['type' => 'action', 'key' => 'end'],
            ['type' => 'action', 'key' => 'log'],
        ]);

        $run = $this->engine()->run((int) $a->getKey(), []);
        $this->assertSame('completed', (string) $run->status);
        $this->assertSame(1, count($run->steps())); // log never ran
    }

    public function test_custom_action_can_be_registered(): void
    {
        $a = $this->builder()->create('Custom', 'custom.event', [
            ['type' => 'action', 'key' => 'my_plugin_action'],
        ]);

        $engine = $this->engine()->register(new class implements \App\Contracts\Automation\AutomationAction {
            public function key(): string
            {
                return 'my_plugin_action';
            }

            public function run(array $context, array $config): array
            {
                return ['plugin' => 'ran'];
            }
        });

        $run = $engine->run((int) $a->getKey(), []);
        $this->assertSame('completed', (string) $run->status);
        $steps = $run->steps();
        $this->assertSame('completed', (string) $steps[0]['status']);
    }

    public function test_versioning_publish_and_rollback(): void
    {
        $a = $this->builder()->create('Versioned', 'job.published', [
            ['type' => 'action', 'key' => 'log'],
        ]);
        $id = (int) $a->getKey();

        $this->builder()->publish($id);          // v1
        $v2 = $this->builder()->publish($id);     // v2
        $this->assertSame(2, (int) $v2->version);

        $v3 = $this->builder()->rollback($id, 1); // restores v1 as v3
        $this->assertSame(3, (int) $v3->version);
        $this->assertSame(3, (int) AutomationVersion::activeFor($id)->version);
        $this->assertSame(3, AutomationVersion::query()->where('automation_id', '=', $id)->count());
    }

    public function test_clone_duplicates_automation(): void
    {
        $a = $this->builder()->create('Original', 'offer.accepted', [
            ['type' => 'condition', 'key' => 'always'],
            ['type' => 'action', 'key' => 'notify', 'config' => ['channel' => 'email']],
        ]);

        $copy = $this->builder()->clone((int) $a->getKey());
        $this->assertTrue((int) $a->getKey() !== (int) $copy->getKey());
        $this->assertSame(2, count($copy->steps()));
        $this->assertSame('offer.accepted', (string) $copy->trigger_event);
    }
};
