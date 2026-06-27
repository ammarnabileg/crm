<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A directed connection between two workflow nodes (docs/51 §8). An edge may carry
 * a `condition` (JSON like {field, op, value}) so an If/Else node can branch on the
 * run context (e.g. score < 60). A null condition is an unconditional / default
 * (else) edge. Evaluated in `sort_order`. Tenant-scoped.
 */
final class WorkflowEdge extends Model
{
    protected static string $table = 'workflow_edges';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'workflow_id', 'from_node_id', 'to_node_id', 'label', 'condition', 'sort_order',
    ];

    protected static array $casts = [
        'condition'  => 'array',
        'sort_order' => 'int',
    ];
}
