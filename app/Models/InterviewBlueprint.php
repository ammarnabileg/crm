<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * An interview Blueprint (docs/51 §6): the reusable, role-family template the AI
 * Interview Engine runs from. A blueprint owns an ordered set of weighted
 * `blueprint_sections` (each with typed `blueprint_section_rules`), and a published
 * `blueprint_versions` snapshot is the frozen definition the runtime executes.
 * Tenant-scoped, soft-deletable, versioned.
 */
final class InterviewBlueprint extends Model
{
    protected static string $table = 'interview_blueprints';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'name', 'slug', 'role_family', 'description', 'is_active', 'version',
        'created_by',
    ];

    protected static array $casts = [
        'is_active' => 'bool',
        'version'   => 'int',
    ];

    /** @return array<int, array<string,mixed>> The blueprint's sections (ordered). */
    public function sections(): array
    {
        return self::db()->table('blueprint_sections')
            ->where('blueprint_id', '=', (int) $this->getKey())
            ->orderBy('sort_order')
            ->get();
    }
}
