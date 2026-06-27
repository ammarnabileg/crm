<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A Multi-Agent layer expert (docs/51 §2). System rows have a NULL workspace_id
 * (platform defaults seeded by migration 0041); a tenant may override or add an
 * agent with its own workspace_id. Like {@see InterviewState}, this model is NOT
 * tenant-scoped because the system rows are global — the AgentRunner resolves the
 * effective catalog by merging system + tenant rows explicitly (tenant overrides
 * the system row of the same key).
 */
final class AiAgent extends Model
{
    protected static string $table = 'ai_agents';
    protected static bool $tenantScoped = false;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'workspace_id', 'key', 'name', 'agent_type', 'description',
        'weight', 'config', 'is_active', 'is_system', 'sort_order',
    ];

    protected static array $casts = [
        'config'     => 'array',
        'weight'     => 'float',
        'is_active'  => 'bool',
        'is_system'  => 'bool',
        'sort_order' => 'int',
    ];
}
