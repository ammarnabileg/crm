<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A weighted criterion of an Evaluation Template (docs/51 §9) — e.g. "Technical
 * depth", "Communication", "Problem solving". Belongs to an `evaluation_forms`
 * rubric; its `weight` drives the Decision Engine's aggregation and its
 * min/max bound the per-criterion score. Tenant-scoped, soft-deletable.
 */
final class EvaluationFormField extends Model
{
    protected static string $table = 'evaluation_form_fields';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'form_id', 'label', 'key', 'description', 'field_type_id', 'category_id',
        'weight', 'max_value', 'min_value', 'options', 'is_required', 'sort_order',
    ];

    protected static array $casts = [
        'weight'      => 'float',
        'max_value'   => 'float',
        'min_value'   => 'float',
        'options'     => 'array',
        'is_required' => 'bool',
        'sort_order'  => 'int',
    ];
}
