<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Tracks a user's progress through a named onboarding flow within a workspace
 * context. Not auto tenant-scoped (it is keyed by user first); reads are
 * filtered explicitly by user + workspace.
 */
final class OnboardingProgress extends Model
{
    protected static string $table = 'onboarding_progress';
    protected static bool $tenantScoped = false;

    protected static array $fillable = [
        'user_id', 'workspace_id', 'flow', 'current_step',
        'completed_steps', 'is_completed', 'completed_at',
    ];

    protected static array $casts = [
        'completed_steps' => 'array',
        'is_completed'    => 'bool',
        'current_step'    => 'int',
    ];

    public static function forUser(int $userId, ?int $workspaceId, string $flow): ?self
    {
        $query = static::withoutTenantScope()
            ->where('user_id', '=', $userId)
            ->where('flow', '=', $flow);

        $workspaceId === null ? $query->whereNull('workspace_id') : $query->where('workspace_id', '=', $workspaceId);

        $row = $query->first();

        return $row ? static::hydrate($row) : null;
    }
}
