<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * An immutable published snapshot of an Evaluation Template (docs/51 §9). When a
 * rubric is published the form + its criteria are frozen into `snapshot` (JSON),
 * so the Decision Engine always scores against a fixed rubric and later edits to
 * the live form never rewrite past decisions. Append-only by convention (no soft
 * deletes); exactly one version per form is `is_active`.
 */
final class EvaluationFormVersion extends Model
{
    protected static string $table = 'evaluation_form_versions';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'form_id', 'version', 'snapshot', 'notes', 'is_active',
        'published_by', 'published_at',
    ];

    protected static array $casts = [
        'snapshot'  => 'array',
        'version'   => 'int',
        'is_active' => 'bool',
    ];

    /** The active published version for a form, or null if none published yet. */
    public static function activeFor(int $formId): ?self
    {
        $row = self::query()
            ->where('form_id', '=', $formId)
            ->where('is_active', '=', 1)
            ->orderBy('version', 'desc')
            ->first();

        return $row !== null ? self::hydrate($row) : null;
    }
}
