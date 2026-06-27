<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A visual, no-code interview workflow (docs/51 §8): a directed graph of typed
 * nodes (`workflow_nodes`) connected by `workflow_edges`, published as immutable
 * `workflow_versions` and executed by the WorkflowRuntime. Tenant-scoped,
 * soft-deletable, versioned.
 */
final class InterviewWorkflow extends Model
{
    protected static string $table = 'interview_workflows';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'name', 'slug', 'description', 'is_active', 'version', 'created_by',
    ];

    protected static array $casts = [
        'is_active' => 'bool',
        'version'   => 'int',
    ];

    /** @return array<int, array<string,mixed>> Nodes ordered for display. */
    public function nodes(): array
    {
        return self::db()->table('workflow_nodes')
            ->where('workflow_id', '=', (int) $this->getKey())
            ->orderBy('sort_order')
            ->get();
    }

    /** @return array<int, array<string,mixed>> Edges ordered for evaluation. */
    public function edges(): array
    {
        return self::db()->table('workflow_edges')
            ->where('workflow_id', '=', (int) $this->getKey())
            ->orderBy('sort_order')
            ->get();
    }
}
