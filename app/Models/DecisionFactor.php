<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * One criterion's contribution to a Decision Record (docs/51 §3, §15 Explainable
 * AI): raw_score × weight = weighted_score, with a human-readable rationale. The
 * sum of `weighted_score` across a decision's factors reconstructs its overall
 * score — making every decision auditable and explainable. Tenant-scoped,
 * append-only.
 */
final class DecisionFactor extends Model
{
    protected static string $table = 'decision_factors';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $timestamps = false;

    protected static array $fillable = [
        'decision_id', 'criterion_key', 'criterion_label', 'weight',
        'raw_score', 'max_score', 'weighted_score', 'rationale', 'sort_order',
    ];

    protected static array $casts = [
        'weight'         => 'float',
        'raw_score'      => 'float',
        'max_score'      => 'float',
        'weighted_score' => 'float',
        'sort_order'     => 'int',
    ];
}
