<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * An automation rule (docs/51 Automation Engine): a TRIGGER event bound to ordered
 * condition + action steps (`automation_steps`). Versioned via `automation_versions`;
 * executions recorded in `automation_runs`. Tenant-scoped, soft-deletable.
 */
final class Automation extends Model
{
    protected static string $table = 'automations';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'name', 'slug', 'description', 'trigger_event', 'is_active', 'version', 'created_by',
    ];

    protected static array $casts = [
        'is_active' => 'bool',
        'version'   => 'int',
    ];

    /** @return array<int, array<string,mixed>> Ordered steps (conditions then actions by sort_order). */
    public function steps(): array
    {
        return self::db()->table('automation_steps')
            ->where('automation_id', '=', (int) $this->getKey())
            ->orderBy('sort_order')
            ->get();
    }
}
