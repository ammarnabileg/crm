<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A typed node in an interview workflow graph (docs/51 §8). `node_key` is the
 * stable id edges reference; `type_id` is a `workflow_node_type` lookup; `config`
 * carries per-node settings (e.g. the question id, the criterion to score). The
 * visual editor uses position_x/position_y. Tenant-scoped.
 */
final class WorkflowNode extends Model
{
    protected static string $table = 'workflow_nodes';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'workflow_id', 'node_key', 'type_id', 'label', 'config',
        'position_x', 'position_y', 'sort_order',
    ];

    protected static array $casts = [
        'config'     => 'array',
        'position_x' => 'int',
        'position_y' => 'int',
        'sort_order' => 'int',
    ];
}
