<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * The Decision Engine's output (docs/51 §3): one aggregated, explainable decision
 * for an interview/application, scored against a frozen evaluation template
 * version. Holds the weighted overall + normalized (0–100) score, pass/fail vs the
 * rubric threshold, and an optional recommendation (lookup). The per-criterion
 * breakdown lives in `decision_factors` (explainable AI). Distinct from
 * `application_decisions`, which audits pipeline status moves. Tenant-scoped.
 */
final class DecisionRecord extends Model
{
    protected static string $table = 'decision_records';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'interview_id', 'application_id', 'form_version_id',
        'overall_score', 'max_score', 'normalized_score', 'pass_threshold',
        'passed', 'recommendation_id', 'is_ai', 'confidence', 'summary', 'meta',
        'decided_by', 'decided_at',
    ];

    protected static array $casts = [
        'overall_score'    => 'float',
        'max_score'        => 'float',
        'normalized_score' => 'float',
        'pass_threshold'   => 'float',
        'passed'           => 'bool',
        'confidence'       => 'float',
        'is_ai'            => 'bool',
        'meta'             => 'array',
    ];

    /** @return array<int, array<string,mixed>> The explainable per-criterion factors. */
    public function factors(): array
    {
        return self::db()->table('decision_factors')
            ->where('decision_id', '=', (int) $this->getKey())
            ->orderBy('sort_order')
            ->get();
    }
}
