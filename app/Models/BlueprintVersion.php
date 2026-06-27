<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * An immutable published snapshot of an interview Blueprint (docs/51 §6). When a
 * blueprint is published the header + its sections + each section's rules are frozen
 * into `snapshot` (JSON), so the interview runtime always executes against a fixed
 * definition and later edits to the live blueprint never rewrite past interviews.
 * Append-only by convention (no soft deletes); exactly one version per blueprint is
 * `is_active`.
 */
final class BlueprintVersion extends Model
{
    protected static string $table = 'blueprint_versions';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'blueprint_id', 'version', 'snapshot', 'notes', 'is_active',
        'published_by', 'published_at',
    ];

    protected static array $casts = [
        'snapshot'  => 'array',
        'version'   => 'int',
        'is_active' => 'bool',
    ];

    /** The active published version for a blueprint, or null if none published yet. */
    public static function activeFor(int $blueprintId): ?self
    {
        $row = self::query()
            ->where('blueprint_id', '=', $blueprintId)
            ->where('is_active', '=', 1)
            ->orderBy('version', 'desc')
            ->first();

        return $row !== null ? self::hydrate($row) : null;
    }
}
