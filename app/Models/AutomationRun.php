<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * One execution of an automation (docs/51 Automation Engine execution log). Records
 * the trigger, status (running|completed|failed|skipped — code-validated VARCHAR),
 * the event context and timing. The per-step debugger trace is in
 * `automation_run_steps`. Tenant-scoped.
 */
final class AutomationRun extends Model
{
    protected static string $table = 'automation_runs';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'automation_id', 'trigger_event', 'status', 'context', 'error',
        'started_at', 'ended_at', 'duration_ms',
    ];

    protected static array $casts = [
        'context'     => 'array',
        'duration_ms' => 'int',
    ];

    /** @return array<int, array<string,mixed>> The ordered step trace (debugger). */
    public function steps(): array
    {
        return self::db()->table('automation_run_steps')
            ->where('run_id', '=', (int) $this->getKey())
            ->orderBy('sequence')
            ->get();
    }
}
