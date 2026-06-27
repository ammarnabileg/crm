<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A talent pool (docs/53 ATS Talent Pools) — a named, tenant-scoped collection of
 * candidates a recruiter sources/nurtures outside any single job. Holds a cached
 * `candidate_count` kept in sync by the TalentPool service. Tenant-scoped,
 * soft-deletable.
 */
final class Pool extends Model
{
    protected static string $table = 'pools';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'name', 'slug', 'description', 'type_id', 'owner_user_id', 'department_id',
        'is_shared', 'candidate_count', 'created_by',
    ];

    protected static array $casts = [
        'is_shared'       => 'bool',
        'candidate_count' => 'int',
    ];
}
