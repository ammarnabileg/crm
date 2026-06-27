<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A Human-in-the-Loop review of a Decision Engine output (docs/51 Human-in-the-Loop,
 * §20): one oversight action a person took on a `decision_records` row —
 * approve / edit / reject / request_changes — with the reason and, for an edit, the
 * exact field changes applied. Append-only (no updated_at): the review history is an
 * immutable audit trail. Tenant-scoped.
 */
final class DecisionReview extends Model
{
    protected static string $table = 'decision_reviews';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $timestamps = false;

    protected static array $fillable = [
        'decision_id', 'reviewer_id', 'action', 'reason', 'changes', 'created_at',
    ];

    protected static array $casts = [
        'changes' => 'array',
    ];
}
