<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workflow\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Workflow\Domain\FormulaEvaluator;
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
        private readonly FormulaEvaluator $formula,
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
     * Run one workflow on demand (the builder's "Run now" / Manual Trigger), with
     * an optional sample payload. Returns false if the workflow does not exist.
     *
     * @param  array<string, mixed>  $payload
     */
    public function runById(string $workspaceId, string $workflowId, array $payload = [], ?string $actorUserId = null): bool
    {
        $workflow = $this->workflows->find($workspaceId, $workflowId);
        if ($workflow === null) {
            return false;
        }

        $this->runWorkflow($workflow, $payload + ['workspace_id' => $workspaceId], $actorUserId);

        return true;
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

        // The payload is an evolving variable map: Set-Variable and Formula nodes
        // write into it, so later steps and {{tokens}} can read computed values.
        $context = ['workspace_id' => $workspaceId, 'payload' => $payload, 'actor' => $actorUserId];
        $done = 0;

        try {
            foreach ($steps as $index => $step) {
                $action = (string) ($step['action'] ?? 'noop');

                if (isset($step['condition']) && ! $this->matches($step['condition'], $context['payload'])) {
                    $this->recordStep($workspaceId, $executionId, $index, $action, 'skipped', 'condition not met');

                    continue;
                }

                // Variable-producing nodes are an engine/context concern (they mutate
                // the variable map) — handled here, not in the side-effect executor.
                if ($action === 'variables.set' || $action === 'logic.formula') {
                    $output = $this->setVariable($action, $step['params'] ?? [], $context['payload']);
                    $this->recordStep($workspaceId, $executionId, $index, $action, 'completed', $output);
                    $done++;

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

    /**
     * Handle a variable-producing node (Set Variable / Formula). Mutates the
     * evolving variable map and returns a log line. A Formula runs in the
     * sandboxed {@see FormulaEvaluator} — no raw code executes on the server.
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $payload  the evolving variable map (by reference)
     */
    private function setVariable(string $action, array $params, array &$payload): string
    {
        $name = trim((string) ($params['name'] ?? '')) ?: 'result';

        if ($action === 'logic.formula') {
            $expr = (string) ($params['expression'] ?? '');
            $value = $expr === '' ? '' : $this->formula->evaluate($expr, $payload);
        } else {
            $value = $this->resolveTokens((string) ($params['value'] ?? ''), $payload);
        }

        if (is_scalar($value) || $value === null) {
            $payload[$name] = $value;
        }

        $shown = is_bool($value) ? ($value ? 'true' : 'false') : (is_scalar($value) || $value === null ? (string) $value : (string) json_encode($value));

        return $name . ' = ' . $shown;
    }

    /** Substitute {{field}} tokens from the variable map (picker-inserted, not code). */
    private function resolveTokens(string $value, array $payload): string
    {
        if (! str_contains($value, '{{')) {
            return $value;
        }

        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', static function (array $m) use ($payload): string {
            $v = $payload[$m[1]] ?? '';

            return is_scalar($v) ? (string) $v : (string) json_encode($v);
        }, $value);
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
