<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * An immutable published snapshot of a workflow graph (docs/51 §8, §Versioning).
 * A run always executes a frozen version, so editing the live workflow never
 * changes in-flight or historical runs. Exactly one version per workflow is
 * `is_active`. Append-only by convention. Tenant-scoped.
 */
final class WorkflowVersion extends Model
{
    protected static string $table = 'workflow_versions';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'workflow_id', 'version', 'snapshot', 'notes', 'is_active',
        'published_by', 'published_at',
    ];

    protected static array $casts = [
        'snapshot'  => 'array',
        'version'   => 'int',
        'is_active' => 'bool',
    ];

    /** The active published version for a workflow, or null if none published. */
    public static function activeFor(int $workflowId): ?self
    {
        $row = self::query()
            ->where('workflow_id', '=', $workflowId)
            ->where('is_active', '=', 1)
            ->orderBy('version', 'desc')
            ->first();

        return $row !== null ? self::hydrate($row) : null;
    }
}
