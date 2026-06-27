<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * One node execution within a workflow run (docs/51 §8) — the reproducible audit
 * trail. References the node by `node_key`/`node_type` (not an FK) because the run
 * executes a frozen version snapshot whose live nodes may later change. Records
 * input/output, the branch `decision` taken, and a `status` (workflow_step_status
 * lookup). Tenant-scoped, append-only.
 */
final class WorkflowRunStep extends Model
{
    protected static string $table = 'workflow_run_steps';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $timestamps = false;

    protected static array $fillable = [
        'run_id', 'node_key', 'node_type', 'status_id', 'sequence',
        'input', 'output', 'decision', 'entered_at', 'exited_at',
    ];

    protected static array $casts = [
        'input'    => 'array',
        'output'   => 'array',
        'sequence' => 'int',
    ];
}
