<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Core\Database;

/**
 * Read model for the Automation web layer — lists a workspace's automation rules
 * and loads one with its steps + recent run history for the detail page.
 *
 * Tenant-scoped by construction: every query filters by the active workspace id
 * (passed in by the controller from tenant()->id()), so one workspace can never
 * see another's automations. Writes go through AutomationBuilder; this is reads only.
 */
final class AutomationDirectory
{
    private Database $db;

    public function __construct(private readonly int $workspaceId, ?Database $db = null)
    {
        $this->db = $db ?? app('db');
    }

    /**
     * Every automation in the workspace (newest first) with its step count and the
     * status/time of its most recent run.
     *
     * @return array<int, array<string,mixed>>
     */
    public function all(): array
    {
        $rows = $this->db->table('automations')
            ->select('id', 'uuid', 'name', 'slug', 'trigger_event', 'is_active', 'version', 'created_at')
            ->where('workspace_id', '=', $this->workspaceId)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get();

        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $stepCounts = $this->stepCounts($ids);
        $lastRuns = $this->lastRuns($ids);

        return array_map(function (array $row) use ($stepCounts, $lastRuns): array {
            $id = (int) $row['id'];

            return [
                'id'            => $id,
                'name'          => (string) $row['name'],
                'slug'          => (string) $row['slug'],
                'trigger_event' => (string) $row['trigger_event'],
                'is_active'     => (bool) $row['is_active'],
                'version'       => (int) $row['version'],
                'step_count'    => (int) ($stepCounts[$id] ?? 0),
                'last_run'      => $lastRuns[$id] ?? null,
                'created_at'    => (string) ($row['created_at'] ?? ''),
            ];
        }, $rows);
    }

    /**
     * One automation with its ordered steps + recent runs, or null when the id is
     * unknown in this workspace (the cross-tenant guard → controller 404).
     *
     * @return array<string,mixed>|null
     */
    public function find(int $id): ?array
    {
        $row = $this->db->table('automations')
            ->where('id', '=', $id)
            ->where('workspace_id', '=', $this->workspaceId)
            ->whereNull('deleted_at')
            ->first();

        if ($row === null) {
            return null;
        }

        $steps = $this->db->table('automation_steps')
            ->select('step_type', 'key', 'config', 'sort_order')
            ->where('automation_id', '=', $id)
            ->orderBy('sort_order')
            ->get();

        return [
            'id'            => (int) $row['id'],
            'name'          => (string) $row['name'],
            'slug'          => (string) $row['slug'],
            'description'   => (string) ($row['description'] ?? ''),
            'trigger_event' => (string) $row['trigger_event'],
            'is_active'     => (bool) $row['is_active'],
            'version'       => (int) $row['version'],
            'steps'         => array_map(static fn (array $s): array => [
                'type'   => (string) $s['step_type'],
                'key'    => (string) $s['key'],
                'config' => $s['config'] !== null ? (string) $s['config'] : '',
            ], $steps),
            'runs' => $this->recentRuns($id),
        ];
    }

    /**
     * @param int[] $ids
     * @return array<int,int>
     */
    private function stepCounts(array $ids): array
    {
        $rows = $this->db->table('automation_steps')
            ->select('automation_id')
            ->whereIn('automation_id', $ids)
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $aid = (int) $row['automation_id'];
            $counts[$aid] = ($counts[$aid] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Most recent run per automation (status + time).
     *
     * @param int[] $ids
     * @return array<int, array{status:string, at:string}>
     */
    private function lastRuns(array $ids): array
    {
        $rows = $this->db->table('automation_runs')
            ->select('automation_id', 'status', 'created_at')
            ->whereIn('automation_id', $ids)
            ->orderBy('automation_id', 'asc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $aid = (int) $row['automation_id'];
            if (isset($out[$aid])) {
                continue;
            }
            $out[$aid] = ['status' => (string) $row['status'], 'at' => (string) ($row['created_at'] ?? '')];
        }

        return $out;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function recentRuns(int $automationId, int $limit = 15): array
    {
        $rows = $this->db->table('automation_runs')
            ->select('status', 'trigger_event', 'created_at')
            ->where('automation_id', '=', $automationId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        return array_map(static fn (array $r): array => [
            'status'        => (string) ($r['status'] ?? ''),
            'trigger_event' => (string) ($r['trigger_event'] ?? ''),
            'created_at'    => (string) ($r['created_at'] ?? ''),
        ], $rows);
    }
}
