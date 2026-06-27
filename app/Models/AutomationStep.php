<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * One step of an automation (docs/51 Automation Engine): a condition or an action,
 * identified by `key` (resolved from the engine registry) with per-step `config`.
 * Tenant-scoped.
 */
final class AutomationStep extends Model
{
    protected static string $table = 'automation_steps';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'automation_id', 'step_type', 'key', 'config', 'sort_order',
    ];

    protected static array $casts = [
        'config'     => 'array',
        'sort_order' => 'int',
    ];
}
