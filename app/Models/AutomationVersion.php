<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * An immutable published snapshot of an automation (docs/51 — compare / rollback /
 * restore / clone). Append-only; exactly one version per automation is `is_active`.
 * Tenant-scoped.
 */
final class AutomationVersion extends Model
{
    protected static string $table = 'automation_versions';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'automation_id', 'version', 'snapshot', 'notes', 'is_active', 'published_by', 'published_at',
    ];

    protected static array $casts = [
        'snapshot'  => 'array',
        'version'   => 'int',
        'is_active' => 'bool',
    ];

    /** The active published version for an automation, or null. */
    public static function activeFor(int $automationId): ?self
    {
        $row = self::query()
            ->where('automation_id', '=', $automationId)
            ->where('is_active', '=', 1)
            ->orderBy('version', 'desc')
            ->first();

        return $row !== null ? self::hydrate($row) : null;
    }
}
