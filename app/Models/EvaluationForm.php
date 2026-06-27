<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * An Evaluation Template (docs/51 §9): a reusable, weighted, thresholded scoring
 * rubric. This is the existing D9 "configurable scorecard" reused as the AI
 * engine's evaluation template — its `evaluation_form_fields` are the weighted
 * criteria, and a published `evaluation_form_versions` snapshot is what the
 * Decision Engine scores against. Tenant-scoped, soft-deletable, versioned.
 */
final class EvaluationForm extends Model
{
    protected static string $table = 'evaluation_forms';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'name', 'slug', 'description', 'scope_id', 'scoring_type_id',
        'max_score', 'pass_threshold', 'version', 'is_active', 'is_default',
        'created_by',
    ];

    protected static array $casts = [
        'max_score'      => 'float',
        'pass_threshold' => 'float',
        'version'        => 'int',
        'is_active'      => 'bool',
        'is_default'     => 'bool',
    ];

    /** @return array<int, array<string,mixed>> The form's criteria (ordered). */
    public function fields(): array
    {
        return self::db()->table('evaluation_form_fields')
            ->where('form_id', '=', (int) $this->getKey())
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get();
    }
}
