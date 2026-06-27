<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A section of an interview Blueprint (docs/51 §6) — e.g. "Technical Assessment",
 * "Problem Solving", "Behavioral". Belongs to an `interview_blueprints` row; its
 * `weight` drives how heavily the section contributes, `difficulty` steers question
 * generation, and its `blueprint_section_rules` carry the question/scoring/follow-up
 * guidance. Tenant-scoped, versioned via blueprint snapshots.
 */
final class BlueprintSection extends Model
{
    protected static string $table = 'blueprint_sections';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'blueprint_id', 'key', 'title', 'objective', 'weight', 'difficulty',
        'config', 'sort_order',
    ];

    protected static array $casts = [
        'config'     => 'array',
        'weight'     => 'float',
        'sort_order' => 'int',
    ];

    /** @return array<int, array<string,mixed>> The section's typed rules (ordered). */
    public function rules(): array
    {
        return self::db()->table('blueprint_section_rules')
            ->where('section_id', '=', (int) $this->getKey())
            ->orderBy('sort_order')
            ->get();
    }
}
