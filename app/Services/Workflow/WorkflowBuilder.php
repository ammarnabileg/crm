<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Models\InterviewWorkflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use App\Models\WorkflowVersion;
use RuntimeException;

/**
 * Workflow Builder service (docs/51 §8, AI Interview Engine P3).
 *
 * Builds and validates an interview workflow as a directed graph and publishes
 * immutable version snapshots the WorkflowRuntime executes. Validation enforces a
 * runnable graph: exactly one Start, at least one Finish, valid node types, edges
 * that reference real nodes, no dangling exits, and every node reachable from
 * Start. All operations are tenant-scoped through the Model layer (fail-closed).
 */
final class WorkflowBuilder
{
    /**
     * Validate a graph definition. Returns human-readable issues — empty = valid.
     *
     * @param array<int, array<string,mixed>> $nodes each: node_key, type (+ label)
     * @param array<int, array<string,mixed>> $edges each: from, to (+ condition)
     * @return string[]
     */
    public function validate(array $nodes, array $edges): array
    {
        $issues = [];
        $types = config('workflow.node_types', []);

        if ($nodes === []) {
            return ['A workflow needs at least one node.'];
        }

        $keys = [];
        $byType = [];
        foreach ($nodes as $i => $n) {
            $key = (string) ($n['node_key'] ?? '');
            $type = (string) ($n['type'] ?? '');
            if ($key === '') {
                $issues[] = "Node #{$i} is missing a node_key.";
            } elseif (isset($keys[$key])) {
                $issues[] = "Duplicate node_key '{$key}'.";
            } else {
                $keys[$key] = true;
            }
            if (! in_array($type, $types, true)) {
                $issues[] = "Node '{$key}' has an unknown type '{$type}'.";
            }
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }

        $startCount = $byType['start'] ?? 0;
        if ($startCount !== 1) {
            $issues[] = "A workflow must have exactly one 'start' node ({$startCount} found).";
        }
        if (($byType['finish'] ?? 0) < 1) {
            $issues[] = "A workflow must have at least one 'finish' node.";
        }

        // Edge endpoints must reference existing nodes; track in/out degree.
        $outDegree = [];
        $inDegree = [];
        $adjacency = [];
        foreach ($edges as $i => $e) {
            $from = (string) ($e['from'] ?? '');
            $to = (string) ($e['to'] ?? '');
            if (! isset($keys[$from])) {
                $issues[] = "Edge #{$i} starts from unknown node '{$from}'.";
            }
            if (! isset($keys[$to])) {
                $issues[] = "Edge #{$i} points to unknown node '{$to}'.";
            }
            $outDegree[$from] = ($outDegree[$from] ?? 0) + 1;
            $inDegree[$to] = ($inDegree[$to] ?? 0) + 1;
            $adjacency[$from][] = $to;
        }

        // Structural rules per node type.
        foreach ($nodes as $n) {
            $key = (string) ($n['node_key'] ?? '');
            $type = (string) ($n['type'] ?? '');
            $out = $outDegree[$key] ?? 0;
            $in = $inDegree[$key] ?? 0;

            if ($type === 'start' && $in > 0) {
                $issues[] = "The 'start' node '{$key}' must have no incoming edges.";
            }
            if ($type === 'finish' && $out > 0) {
                $issues[] = "The 'finish' node '{$key}' must have no outgoing edges.";
            }
            if ($type !== 'finish' && $out === 0) {
                $issues[] = "Node '{$key}' ({$type}) has no outgoing edge — the run would dead-end.";
            }
        }

        // Reachability from the (first) start node.
        if ($startCount === 1) {
            $start = '';
            foreach ($nodes as $n) {
                if (($n['type'] ?? '') === 'start') {
                    $start = (string) $n['node_key'];
                    break;
                }
            }
            $reachable = $this->reachable($start, $adjacency);
            foreach ($keys as $key => $_) {
                if (! isset($reachable[$key])) {
                    $issues[] = "Node '{$key}' is not reachable from start.";
                }
            }
        }

        return $issues;
    }

    /**
     * Create a workflow with its nodes and edges (validated first). $edges connect
     * nodes by node_key. Does NOT publish — call publish() to freeze a version.
     *
     * @param array<int, array<string,mixed>> $nodes
     * @param array<int, array<string,mixed>> $edges
     */
    public function create(string $name, array $nodes, array $edges, ?int $userId = null): InterviewWorkflow
    {
        $issues = $this->validate($nodes, $edges);
        if ($issues !== []) {
            throw new RuntimeException('Invalid workflow: ' . implode(' ', $issues));
        }

        $workflow = InterviewWorkflow::create([
            'name'       => $name,
            'slug'       => $this->uniqueSlug($name),
            'is_active'  => 1,
            'version'    => 0,
            'created_by' => $userId,
        ]);
        $workflowId = (int) $workflow->getKey();

        // Insert nodes, building a node_key => id map for the edges.
        $keyToId = [];
        $sort = 0;
        foreach ($nodes as $n) {
            $typeId = lookup_id('workflow_node_type', (string) $n['type']);
            $node = WorkflowNode::create([
                'workflow_id' => $workflowId,
                'node_key'    => (string) $n['node_key'],
                'type_id'     => $typeId,
                'label'       => (string) ($n['label'] ?? $n['node_key']),
                'config'      => isset($n['config'])
                    ? json_encode($n['config'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : null,
                'position_x'  => (int) ($n['position_x'] ?? 0),
                'position_y'  => (int) ($n['position_y'] ?? 0),
                'sort_order'  => $sort++,
            ]);
            $keyToId[(string) $n['node_key']] = (int) $node->getKey();
        }

        $sort = 0;
        foreach ($edges as $e) {
            WorkflowEdge::create([
                'workflow_id'  => $workflowId,
                'from_node_id' => $keyToId[(string) $e['from']],
                'to_node_id'   => $keyToId[(string) $e['to']],
                'label'        => $e['label'] ?? null,
                'condition'    => isset($e['condition'])
                    ? json_encode($e['condition'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : null,
                'sort_order'   => $sort++,
            ]);
        }

        return $workflow;
    }

    /**
     * Publish an immutable snapshot (nodes keyed by node_key + edge list) as the new
     * active version. Validates the live graph, supersedes the prior active version
     * and bumps the workflow's version counter.
     */
    public function publish(int $workflowId, ?int $userId = null, ?string $notes = null): WorkflowVersion
    {
        $workflow = InterviewWorkflow::find($workflowId);
        if ($workflow === null) {
            throw new RuntimeException("Workflow {$workflowId} not found in this workspace.");
        }

        [$nodes, $edges] = $this->graphFor($workflow);

        $issues = $this->validate($nodes, $edges);
        if ($issues !== []) {
            throw new RuntimeException('Cannot publish: ' . implode(' ', $issues));
        }

        $nodeMap = [];
        $start = '';
        foreach ($nodes as $n) {
            $nodeMap[$n['node_key']] = [
                'type'   => $n['type'],
                'label'  => $n['label'],
                'config' => $n['config'],
            ];
            if ($n['type'] === 'start') {
                $start = (string) $n['node_key'];
            }
        }

        $snapshot = ['start' => $start, 'nodes' => $nodeMap, 'edges' => $edges];

        $latest = WorkflowVersion::query()
            ->where('workflow_id', '=', $workflowId)
            ->orderBy('version', 'desc')
            ->first();
        $next = $latest !== null ? ((int) $latest['version']) + 1 : 1;

        WorkflowVersion::query()
            ->where('workflow_id', '=', $workflowId)
            ->where('is_active', '=', 1)
            ->update(['is_active' => 0, 'updated_at' => now()]);

        $version = WorkflowVersion::create([
            'workflow_id'  => $workflowId,
            'version'      => $next,
            'snapshot'     => json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'notes'        => $notes,
            'is_active'    => 1,
            'published_by' => $userId,
            'published_at' => now(),
        ]);

        $workflow->update(['version' => $next]);

        return $version;
    }

    /**
     * Load a workflow's live graph as builder-shaped arrays (types as keys, edges
     * by node_key).
     *
     * @return array{0: array<int,array<string,mixed>>, 1: array<int,array<string,mixed>>}
     */
    public function graphFor(InterviewWorkflow $workflow): array
    {
        $nodeRows = $workflow->nodes();
        $idToKey = [];
        $nodes = [];
        foreach ($nodeRows as $row) {
            $typeKey = $this->lookupKey((int) $row['type_id']);
            $idToKey[(int) $row['id']] = (string) $row['node_key'];
            $config = $row['config'] ?? null;
            if (is_string($config)) {
                $config = json_decode($config, true);
            }
            $nodes[] = [
                'node_key' => (string) $row['node_key'],
                'type'     => $typeKey,
                'label'    => (string) $row['label'],
                'config'   => $config,
            ];
        }

        $edges = [];
        foreach ($workflow->edges() as $row) {
            $condition = $row['condition'] ?? null;
            if (is_string($condition)) {
                $condition = json_decode($condition, true);
            }
            $edges[] = [
                'from'      => $idToKey[(int) $row['from_node_id']] ?? '',
                'to'        => $idToKey[(int) $row['to_node_id']] ?? '',
                'label'     => $row['label'] ?? null,
                'condition' => $condition,
            ];
        }

        return [$nodes, $edges];
    }

    /**
     * @param array<string, string[]> $adjacency
     * @return array<string, true>
     */
    private function reachable(string $start, array $adjacency): array
    {
        $seen = [];
        $stack = [$start];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node === '' || isset($seen[$node])) {
                continue;
            }
            $seen[$node] = true;
            foreach ($adjacency[$node] ?? [] as $next) {
                if (! isset($seen[$next])) {
                    $stack[] = $next;
                }
            }
        }

        return $seen;
    }

    /** Resolve a lookup_values id to its key (workflow_node_type). */
    private function lookupKey(int $id): string
    {
        $key = app('db')->table('lookup_values')->where('id', '=', $id)->value('key');

        return (string) ($key ?? '');
    }

    /** A workspace-unique slug derived from $name. */
    private function uniqueSlug(string $name): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? '', '-');
        if ($base === '') {
            $base = 'workflow';
        }

        $slug = $base;
        $n = 2;
        while (InterviewWorkflow::withTrashed()->where('slug', '=', $slug)->first() !== null) {
            $slug = $base . '-' . $n;
            $n++;
        }

        return $slug;
    }
}
