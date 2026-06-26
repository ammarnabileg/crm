<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Tracks a user's progress through a named onboarding flow within a company
 * context. Not auto tenant-scoped (it is keyed by user first); reads are
 * filtered explicitly by user + company.
 */
final class OnboardingProgress extends Model
{
    protected static string $table = 'onboarding_progress';
    protected static bool $tenantScoped = false;

    protected static array $fillable = [
        'user_id', 'company_id', 'flow', 'current_step',
        'completed_steps', 'is_completed', 'completed_at',
    ];

    protected static array $casts = [
        'completed_steps' => 'array',
        'is_completed'    => 'bool',
        'current_step'    => 'int',
    ];

    public static function forUser(int $userId, ?int $companyId, string $flow): ?self
    {
        $query = static::withoutTenantScope()
            ->where('user_id', '=', $userId)
            ->where('flow', '=', $flow);

        $companyId === null ? $query->whereNull('company_id') : $query->where('company_id', '=', $companyId);

        $row = $query->first();

        return $row ? static::hydrate($row) : null;
    }
}
