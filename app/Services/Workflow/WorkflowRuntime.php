<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Core\Database;
use App\Models\WorkflowRun;
use App\Models\WorkflowRunStep;
use App\Models\WorkflowVersion;
use RuntimeException;

/**
 * Workflow Runtime (docs/51 §8, AI Interview Engine P3) — a step machine that
 * executes a published workflow version snapshot against an interview.
 *
 * It walks the directed graph from the Start node, auto-advancing through
 * deterministic nodes (entry/branch) and PAUSING at 'await' nodes (human approval,
 * candidate answer, or — until P4+ — a model result). The Orchestrator resumes a
 * paused run by injecting the node's output via resume(); the runtime records each
 * step in `workflow_run_steps` for a reproducible, explainable trace. Because runs
 * execute a frozen snapshot, editing the live workflow never disturbs them.
 *
 * Phases 1–3 are model-free: every 'await' node simply pauses and is resumed with
 * injected output, so a workflow runs end-to-end in tests today.
 */
final class WorkflowRuntime
{
    public function __construct(private readonly Database $db)
    {
    }

    public static function make(): self
    {
        return new self(app('db'));
    }

    /**
     * Begin a run of a published version. Drives from Start until the run pauses at
     * an 'await' node or reaches Finish.
     *
     * @param array<string,mixed> $context initial run context (used by conditions)
     */
    public function start(int $versionId, array $context = [], ?int $interviewId = null): WorkflowRun
    {
        $version = WorkflowVersion::find($versionId);
        if ($version === null) {
            throw new RuntimeException("Workflow version {$versionId} not found in this workspace.");
        }

        $snapshot = $this->snapshotOf($version);
        $startKey = (string) ($snapshot['start'] ?? '');
        if ($startKey === '') {
            throw new RuntimeException('Workflow snapshot has no start node.');
        }

        $run = WorkflowRun::create([
            'workflow_id'      => (int) $version->workflow_id,
            'version_id'       => $versionId,
            'interview_id'     => $interviewId,
            'status_id'        => $this->runStatus('running'),
            'current_node_key' => $startKey,
            'context'          => $this->encode($context),
            'started_at'       => now(),
        ]);

        return $this->drive($run, $snapshot, $startKey);
    }

    /**
     * Resume a paused run: complete the awaiting node (store its output), merge any
     * context patch (e.g. a computed score the next condition branches on), then
     * continue from the awaiting node's successor.
     *
     * @param array<string,mixed> $output       result of the awaited node
     * @param array<string,mixed> $contextPatch values to merge into the run context
     */
    public function resume(int $runId, array $output = [], array $contextPatch = []): WorkflowRun
    {
        $run = WorkflowRun::find($runId);
        if ($run === null) {
            throw new RuntimeException("Workflow run {$runId} not found in this workspace.");
        }
        if ($this->runStatusKey($run) !== 'awaiting') {
            throw new RuntimeException('Run is not awaiting input.');
        }

        $version = WorkflowVersion::find((int) $run->version_id);
        if ($version === null) {
            throw new RuntimeException('Run has no published version to resume against.');
        }
        $snapshot = $this->snapshotOf($version);

        $awaitKey = (string) $run->current_node_key;
        $this->completeAwaitingStep($run, $awaitKey, $output);

        if ($contextPatch !== []) {
            $context = array_merge((array) ($run->context ?? []), $contextPatch);
            $run->update(['context' => $this->encode($context)]);
        }

        $next = $this->successor($snapshot['edges'] ?? [], $awaitKey);
        if ($next === null) {
            $this->endRun($run, 'completed', $awaitKey);

            return $run;
        }

        $this->setRun($run, 'running', $next);

        return $this->drive($run, $snapshot, $next);
    }

    // --- internals ---------------------------------------------------------

    /** @param array<string,mixed> $snapshot */
    private function drive(WorkflowRun $run, array $snapshot, string $nodeKey): WorkflowRun
    {
        $nodes = $snapshot['nodes'] ?? [];
        $edges = $snapshot['edges'] ?? [];
        $guard = (int) config('workflow.max_steps', 200);

        while ($guard-- > 0) {
            $node = $nodes[$nodeKey] ?? null;
            if ($node === null) {
                return $this->failRun($run, "Node '{$nodeKey}' missing from snapshot.");
            }
            $type = (string) ($node['type'] ?? '');
            $behavior = (string) config('workflow.behaviors.' . $type, 'await');

            if ($behavior === 'await') {
                $this->recordStep($run, $nodeKey, $type, 'awaiting');
                $this->setRun($run, 'awaiting', $nodeKey);

                return $run;
            }

            if ($behavior === 'terminal') {
                $this->recordStep($run, $nodeKey, $type, 'completed', null, true);
                $this->endRun($run, 'completed', $nodeKey);

                return $run;
            }

            if ($behavior === 'branch') {
                $chosen = $this->chooseEdge($edges, $nodeKey, (array) ($run->context ?? []));
                if ($chosen === null) {
                    return $this->failRun($run, "Condition node '{$nodeKey}' has no matching or default edge.");
                }
                $this->recordStep($run, $nodeKey, $type, 'completed', (string) $chosen['to'], true);
                $nodeKey = (string) $chosen['to'];
                continue;
            }

            // entry / passthrough: a single outgoing edge.
            $next = $this->successor($edges, $nodeKey);
            if ($next === null) {
                return $this->failRun($run, "Node '{$nodeKey}' has no outgoing edge.");
            }
            $this->recordStep($run, $nodeKey, $type, 'completed', $next, true);
            $nodeKey = $next;
        }

        return $this->failRun($run, 'Workflow exceeded max steps (possible cycle).');
    }

    /**
     * Choose the outgoing edge for a condition node: first edge (by order) whose
     * condition matches the context wins; otherwise the default (null-condition)
     * edge, if any.
     *
     * @param array<int,array<string,mixed>> $edges
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function chooseEdge(array $edges, string $fromKey, array $context): ?array
    {
        $default = null;
        foreach ($edges as $edge) {
            if ((string) ($edge['from'] ?? '') !== $fromKey) {
                continue;
            }
            $condition = $edge['condition'] ?? null;
            if ($condition === null || $condition === []) {
                $default ??= $edge;
                continue;
            }
            if ($this->matches((array) $condition, $context)) {
                return $edge;
            }
        }

        return $default;
    }

    /** The first outgoing edge target for a node, or null. */
    private function successor(array $edges, string $fromKey): ?string
    {
        foreach ($edges as $edge) {
            if ((string) ($edge['from'] ?? '') === $fromKey) {
                return (string) $edge['to'];
            }
        }

        return null;
    }

    /**
     * Evaluate a condition {field, op, value} against the run context.
     *
     * @param array<string,mixed> $condition
     * @param array<string,mixed> $context
     */
    private function matches(array $condition, array $context): bool
    {
        $field = (string) ($condition['field'] ?? '');
        $op = (string) ($condition['op'] ?? '==');
        $expected = $condition['value'] ?? null;
        $actual = $context[$field] ?? null;

        return match ($op) {
            '==', 'eq'  => $actual == $expected,
            '!=', 'neq' => $actual != $expected,
            '<', 'lt'   => is_numeric($actual) && (float) $actual < (float) $expected,
            '<=', 'lte' => is_numeric($actual) && (float) $actual <= (float) $expected,
            '>', 'gt'   => is_numeric($actual) && (float) $actual > (float) $expected,
            '>=', 'gte' => is_numeric($actual) && (float) $actual >= (float) $expected,
            'in'        => is_array($expected) && in_array($actual, $expected, false),
            'not_in'    => is_array($expected) && ! in_array($actual, $expected, false),
            default      => false,
        };
    }

    private function recordStep(
        WorkflowRun $run,
        string $nodeKey,
        string $nodeType,
        string $statusKey,
        ?string $decision = null,
        bool $completed = false
    ): void {
        $now = now();
        WorkflowRunStep::create([
            'run_id'     => (int) $run->getKey(),
            'node_key'   => $nodeKey,
            'node_type'  => $nodeType,
            'status_id'  => $this->stepStatus($statusKey),
            'sequence'   => $this->nextSequence((int) $run->getKey()),
            'decision'   => $decision,
            'entered_at' => $now,
            'exited_at'  => $completed ? $now : null,
            'created_at' => $now,
        ]);
    }

    /** Flip the awaiting step for a node to completed and store its output. */
    private function completeAwaitingStep(WorkflowRun $run, string $nodeKey, array $output): void
    {
        WorkflowRunStep::query()
            ->where('run_id', '=', (int) $run->getKey())
            ->where('node_key', '=', $nodeKey)
            ->where('status_id', '=', $this->stepStatus('awaiting'))
            ->update([
                'status_id' => $this->stepStatus('completed'),
                'output'    => $this->encode($output),
                'exited_at' => now(),
            ]);
    }

    private function nextSequence(int $runId): int
    {
        return $this->db->table('workflow_run_steps')
            ->where('run_id', '=', $runId)
            ->count() + 1;
    }

    private function setRun(WorkflowRun $run, string $statusKey, string $currentNodeKey): void
    {
        $run->update([
            'status_id'        => $this->runStatus($statusKey),
            'current_node_key' => $currentNodeKey,
        ]);
    }

    private function endRun(WorkflowRun $run, string $statusKey, string $currentNodeKey): WorkflowRun
    {
        $run->update([
            'status_id'        => $this->runStatus($statusKey),
            'current_node_key' => $currentNodeKey,
            'ended_at'         => now(),
        ]);

        return $run;
    }

    private function failRun(WorkflowRun $run, string $reason): WorkflowRun
    {
        $context = array_merge((array) ($run->context ?? []), ['_error' => $reason]);
        $run->update([
            'status_id'  => $this->runStatus('failed'),
            'context'    => $this->encode($context),
            'ended_at'   => now(),
        ]);

        return $run;
    }

    /** @param array<string,mixed> $snapshot-bearing version */
    private function snapshotOf(WorkflowVersion $version): array
    {
        $snapshot = $version->snapshot; // 'array' cast

        return is_array($snapshot) ? $snapshot : [];
    }

    private function runStatusKey(WorkflowRun $run): string
    {
        $key = $this->db->table('lookup_values')->where('id', '=', (int) $run->status_id)->value('key');

        return (string) ($key ?? '');
    }

    private function runStatus(string $key): int
    {
        return (int) lookup_id('workflow_run_status', $key);
    }

    private function stepStatus(string $key): int
    {
        return (int) lookup_id('workflow_step_status', $key);
    }

    /** @param array<string,mixed> $value */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
