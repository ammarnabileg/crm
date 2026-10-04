<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workflow\Domain;

/**
 * Pure helpers that turn the visual builder's node graph into things the rest of
 * the system understands — WITHOUT any I/O. The canvas saves a graph
 * `{nodes, edges}`; the engine consumes a linear `steps[]`. This class is the
 * bridge: it compiles the graph to steps, writes a plain-language summary, and
 * validates the graph so the UI can colour nodes (docs/WORKFLOW_ENGINE.md).
 *
 * Graph shape (all produced by the builder, never hand-written):
 *   nodes: [{ id, type, x, y, config: {key: value} }]
 *   edges: [{ from, to, branch? }]   // branch: 'true' | 'false' | 'match' | 'default'
 */
final class WorkflowGraph
{
    /**
     * Compile a graph into the engine's ordered step list. Walks from the trigger
     * along edges; a condition node attaches its test to every following step
     * (the engine skips a step whose condition is not met), which models the
     * common "if X, then do A, B …" flow. Branching beyond that is a future step.
     *
     * @param  array{nodes?: list<array<string,mixed>>, edges?: list<array<string,mixed>>}  $graph
     * @return list<array{action: string, params: array<string,mixed>, condition?: array<string,mixed>}>
     */
    public static function compile(array $graph): array
    {
        $nodes = self::nodesById($graph);
        $edges = self::edges($graph);
        if ($nodes === []) {
            return [];
        }

        $start = self::triggerId($nodes) ?? array_key_first($nodes);
        $current = self::nextAfter($start, $edges);
        $pendingCondition = null;
        $steps = [];
        $seen = [];

        while ($current !== null && ! isset($seen[$current]) && isset($nodes[$current])) {
            $seen[$current] = true;
            $node = $nodes[$current];
            $kind = self::kind((string) ($node['type'] ?? ''));
            /** @var array<string,mixed> $config */
            $config = is_array($node['config'] ?? null) ? $node['config'] : [];

            if ($kind === 'condition') {
                $pendingCondition = [
                    'field' => (string) ($config['field'] ?? ''),
                    'op' => (string) ($config['op'] ?? 'equals'),
                    'value' => $config['value'] ?? null,
                ];
                $current = self::nextAfter($current, $edges, 'true');

                continue;
            }

            $step = ['action' => (string) ($node['type'] ?? 'util.noop'), 'params' => $config];
            if ($pendingCondition !== null && $pendingCondition['field'] !== '') {
                $step['condition'] = $pendingCondition;
            }
            $steps[] = $step;

            $current = self::nextAfter($current, $edges);
        }

        return $steps;
    }

    /**
     * A plain-language description of the automation, e.g.
     * "When a candidate applies, if score is greater than 80, generate a summary
     * and create a task." Built from the node labels in {@see NodeCatalog}.
     *
     * @param  array{nodes?: list<array<string,mixed>>, edges?: list<array<string,mixed>>}  $graph
     */
    public static function summarize(array $graph): string
    {
        $nodes = self::nodesById($graph);
        $catalog = NodeCatalog::byType();
        if ($nodes === []) {
            return 'This workflow is empty. Drag a trigger onto the canvas to begin.';
        }

        $triggerId = self::triggerId($nodes);
        $triggerLabel = $triggerId !== null
            ? strtolower((string) ($catalog[(string) $nodes[$triggerId]['type']]['label'] ?? 'a trigger fires'))
            : 'a trigger fires';

        $actions = [];
        $conditionPhrase = '';
        foreach (self::compile($graph) as $step) {
            if (isset($step['condition']) && $conditionPhrase === '') {
                $c = $step['condition'];
                $conditionPhrase = 'if ' . ($c['field'] ?: 'a field') . ' ' . str_replace('_', ' ', (string) $c['op'])
                    . ($c['value'] !== null && $c['value'] !== '' ? ' ' . $c['value'] : '') . ', ';
            }
            $actions[] = strtolower((string) ($catalog[$step['action']]['label'] ?? $step['action']));
        }

        if ($actions === []) {
            return 'When ' . $triggerLabel . ', this workflow does nothing yet — connect an action.';
        }

        return 'When ' . $triggerLabel . ', ' . $conditionPhrase . self::joinList($actions) . '.';
    }

    /**
     * Validate a graph for the builder's status colours. Returns a list of issues;
     * an empty list means the workflow is publishable.
     *
     * @param  array{nodes?: list<array<string,mixed>>, edges?: list<array<string,mixed>>}  $graph
     * @return array{ok: bool, issues: list<string>, node_issues: array<string,string>}
     */
    public static function validate(array $graph): array
    {
        $nodes = self::nodesById($graph);
        $edges = self::edges($graph);
        $catalog = NodeCatalog::byType();
        $issues = [];
        $nodeIssues = [];

        $triggers = array_filter($nodes, static fn (array $n): bool => self::kind((string) ($n['type'] ?? '')) === 'trigger');
        if ($triggers === []) {
            $issues[] = 'Add a trigger so the workflow knows when to run.';
        } elseif (count($triggers) > 1) {
            $issues[] = 'A workflow can have only one trigger.';
        }

        $actionCount = 0;
        foreach ($nodes as $id => $node) {
            $type = (string) ($node['type'] ?? '');
            $kind = self::kind($type);
            if ($kind === 'action') {
                $actionCount++;
            }

            // A configurable node must have at least something filled in — a fully
            // blank node (no field set) is flagged; optional fields stay optional.
            $schema = $catalog[$type]['config'] ?? [];
            /** @var array<string,mixed> $config */
            $config = is_array($node['config'] ?? null) ? $node['config'] : [];
            if (is_array($schema) && $schema !== []) {
                $filled = 0;
                foreach ($schema as $field) {
                    $key = (string) ($field['key'] ?? '');
                    if ($key !== '' && (string) ($config[$key] ?? '') !== '') {
                        $filled++;
                    }
                }
                if ($filled === 0) {
                    $nodeIssues[(string) $id] = 'Configure this step.';
                }
            }

            // Non-trigger nodes must be connected to something upstream.
            if ($kind !== 'trigger' && ! self::hasIncoming((string) $id, $edges)) {
                $nodeIssues[(string) $id] = $nodeIssues[(string) $id] ?? 'Connect this step to the flow.';
            }
        }

        if ($actionCount === 0) {
            $issues[] = 'Add at least one action for the workflow to do something.';
        }

        $ok = $issues === [] && $nodeIssues === [];

        return ['ok' => $ok, 'issues' => array_values($issues), 'node_issues' => $nodeIssues];
    }

    /**
     * @param  array{nodes?: list<array<string,mixed>>}  $graph
     * @return array<string,array<string,mixed>>
     */
    private static function nodesById(array $graph): array
    {
        $out = [];
        foreach (($graph['nodes'] ?? []) as $node) {
            if (is_array($node) && isset($node['id'])) {
                $out[(string) $node['id']] = $node;
            }
        }

        return $out;
    }

    /**
     * @param  array{edges?: list<array<string,mixed>>}  $graph
     * @return list<array<string,mixed>>
     */
    private static function edges(array $graph): array
    {
        $out = [];
        foreach (($graph['edges'] ?? []) as $edge) {
            if (is_array($edge) && isset($edge['from'], $edge['to'])) {
                $out[] = $edge;
            }
        }

        return $out;
    }

    /** @param array<string,array<string,mixed>> $nodes */
    private static function triggerId(array $nodes): ?string
    {
        foreach ($nodes as $id => $node) {
            if (self::kind((string) ($node['type'] ?? '')) === 'trigger') {
                return (string) $id;
            }
        }

        return null;
    }

    /**
     * The id of the node reached by an edge out of $from. If $branch is given,
     * prefer an edge with that branch label, else the first outgoing edge.
     *
     * @param  list<array<string,mixed>>  $edges
     */
    private static function nextAfter(string $from, array $edges, ?string $branch = null): ?string
    {
        $fallback = null;
        foreach ($edges as $edge) {
            if ((string) $edge['from'] !== $from) {
                continue;
            }
            if ($branch !== null && (string) ($edge['branch'] ?? '') === $branch) {
                return (string) $edge['to'];
            }
            $fallback ??= (string) $edge['to'];
        }

        return $fallback;
    }

    /** @param list<array<string,mixed>> $edges */
    private static function hasIncoming(string $nodeId, array $edges): bool
    {
        foreach ($edges as $edge) {
            if ((string) $edge['to'] === $nodeId) {
                return true;
            }
        }

        return false;
    }

    /** Node kind inferred from its type prefix (trigger.* / condition.* / logic.filter). */
    private static function kind(string $type): string
    {
        if (str_starts_with($type, 'trigger.')) {
            return 'trigger';
        }
        if (str_starts_with($type, 'condition.') || $type === 'logic.filter') {
            return 'condition';
        }

        return 'action';
    }

    /** @param list<string> $items */
    private static function joinList(array $items): string
    {
        $items = array_values(array_unique($items));
        if (count($items) === 1) {
            return $items[0];
        }
        $last = array_pop($items);

        return implode(', ', $items) . ' and ' . $last;
    }
}
