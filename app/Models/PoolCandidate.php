<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Membership of a candidate (by user_id) in a talent pool (docs/53 ATS Talent Pools).
 * Optionally bucketed into a `pool_group_id`, tagged with a sourcing `source_id`
 * and a pipeline `stage_id`. Tenant-scoped, soft-deletable.
 */
final class PoolCandidate extends Model
{
    protected static string $table = 'pool_candidates';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'pool_id', 'pool_group_id', 'user_id', 'source_id', 'stage_id',
        'added_by', 'added_at', 'last_contacted_at',
    ];
}
