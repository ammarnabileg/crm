<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A configuration-driven Interview State Machine stage (docs/51 §5). System rows
 * have a NULL workspace_id (platform defaults); a tenant may override/add stages
 * with its own workspace_id. Not tenant-scoped as a model because the system rows
 * are global and resolution merges system + tenant explicitly in the StateMachine.
 */
final class InterviewState extends Model
{
    protected static string $table = 'interview_states';
    protected static bool $tenantScoped = false;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'workspace_id', 'key', 'label', 'description', 'color',
        'sort_order', 'is_initial', 'is_terminal', 'is_system', 'is_active',
        'timeout_minutes', 'meta',
    ];

    protected static array $casts = [
        'meta'        => 'array',
        'is_initial'  => 'bool',
        'is_terminal' => 'bool',
        'is_active'   => 'bool',
        'sort_order'  => 'int',
    ];
}
