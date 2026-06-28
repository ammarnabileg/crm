<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workflow\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;
use Throwable;

/**
 * The central Workflow Engine. Long-running business processes run here, never
 * inside modules (docs/WORKFLOW_ENGINE.md). Triggered by domain events; each run
 * is recorded as an execution with per-step logs. Synchronous now; queue-ready.
 */
final class WorkflowEngine
{
    public function __construct(
        private readonly Connection $connection,
        private readonly WorkflowService $workflows,
        private readonly ActionExecutor $actions,
    ) {
    }

    /**
     * Run every enabled workflow bound to a trigger. Returns the execution count.
     *
     * @param  array<string, mixed>  $payload
     */
    public function runForTrigger(string $workspaceId, string $triggerEvent, array $payload, ?string $actorUserId = null): int
    {
        $count = 0;

        foreach ($this->workflows->findEnabledForTrigger($workspaceId, $triggerEvent) as $workflow) {
            $this->runWorkflow($workflow, $payload, $actorUserId);
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $workflow
     * @param  array<string, mixed>  $payload
     */
    private function runWorkflow(array $workflow, array $payload, ?string $actorUserId): void
    {
        $workspaceId = (string) $workflow['workspace_id'];
        $steps = $workflow['steps'] ?? [];
        $executionId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->statement(
            'INSERT INTO workflow_executions (id, workspace_id, workflow_id, trigger_event, status, payload, steps_total, steps_done, started_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$executionId, $workspaceId, (string) $workflow['id'], (string) $workflow['trigger_event'], 'running', json_encode($payload), count($steps), 0, $now, $now],
        );

        $context = ['workspace_id' => $workspaceId, 'payload' => $payload, 'actor' => $actorUserId];
        $done = 0;

        try {
            foreach ($steps as $index => $step) {
                $action = (string) ($step['action'] ?? 'noop');

                if (isset($step['condition']) && ! $this->matches($step['condition'], $payload)) {
                    $this->recordStep($workspaceId, $executionId, $index, $action, 'skipped', 'condition not met');

                    continue;
                }

                $output = $this->actions->execute($action, $step['params'] ?? [], $context);
                $this->recordStep($workspaceId, $executionId, $index, $action, 'completed', $output);
                $done++;
            }

            $this->finish($executionId, 'completed', $done, null);
        } catch (Throwable $e) {
            $this->recordStep($workspaceId, $executionId, $done, 'error', 'failed', $e->getMessage());
            $this->finish($executionId, 'failed', $done, $e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $condition  {field, op, value}
     * @param  array<string, mixed>  $payload
     */
    private function matches(array $condition, array $payload): bool
    {
        $field = (string) ($condition['field'] ?? '');
        $value = $condition['value'] ?? null;
        $actual = $payload[$field] ?? null;

        return match ((string) ($condition['op'] ?? 'equals')) {
            'equals' => $actual == $value,
            'not_equals' => $actual != $value,
            'contains' => is_string($actual) && is_string($value) && str_contains($actual, $value),
            'exists' => $actual !== null,
            default => false,
        };
    }

    private function recordStep(string $workspaceId, string $executionId, int $index, string $action, string $status, string $output): void
    {
        $this->connection->statement(
            'INSERT INTO workflow_steps (id, workspace_id, execution_id, step_index, action, status, output, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $workspaceId, $executionId, $index, $action, $status, mb_substr($output, 0, 2000), gmdate('Y-m-d H:i:s')],
        );
    }

    private function finish(string $executionId, string $status, int $done, ?string $error): void
    {
        $this->connection->statement(
            'UPDATE workflow_executions SET status = ?, steps_done = ?, error = ?, finished_at = ? WHERE id = ?',
            [$status, $done, $error, gmdate('Y-m-d H:i:s'), $executionId],
        );
    }
}
