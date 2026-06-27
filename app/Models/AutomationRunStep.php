<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * One step's execution within an automation run (docs/51 — the debugger / execution
 * log): which condition/action ran, its status, input/output, error and duration.
 * Tenant-scoped, append-only.
 */
final class AutomationRunStep extends Model
{
    protected static string $table = 'automation_run_steps';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $timestamps = false;

    protected static array $fillable = [
        'run_id', 'step_type', 'step_key', 'status', 'sequence',
        'input', 'output', 'error', 'duration_ms',
    ];

    protected static array $casts = [
        'input'       => 'array',
        'output'      => 'array',
        'sequence'    => 'int',
        'duration_ms' => 'int',
    ];
}
