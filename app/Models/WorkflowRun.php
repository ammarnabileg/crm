<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * One execution of a published workflow version against an interview (docs/51 §8).
 * Tracks the run `status` (workflow_run_status lookup), the `current_node_key`
 * where it is paused/positioned, and a `context` bag the runtime evaluates
 * conditions against. The per-node trace lives in `workflow_run_steps`.
 * Tenant-scoped.
 */
final class WorkflowRun extends Model
{
    protected static string $table = 'workflow_runs';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'workflow_id', 'version_id', 'interview_id', 'status_id',
        'current_node_key', 'context', 'started_at', 'ended_at',
    ];

    protected static array $casts = [
        'context' => 'array',
    ];

    /** @return array<int, array<string,mixed>> The ordered step trace. */
    public function steps(): array
    {
        return self::db()->table('workflow_run_steps')
            ->where('run_id', '=', (int) $this->getKey())
            ->orderBy('sequence')
            ->get();
    }
}
