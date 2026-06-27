<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A recruitment pipeline (docs/53 ATS): an ordered set of `pipeline_stages` an
 * application moves through. May belong to a job, or be a reusable template
 * (`is_template`). Tenant-scoped, soft-deletable.
 */
final class Pipeline extends Model
{
    protected static string $table = 'pipelines';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'job_id', 'name', 'description', 'is_default', 'is_template', 'is_active', 'created_by',
    ];

    protected static array $casts = [
        'is_default'  => 'bool',
        'is_template' => 'bool',
        'is_active'   => 'bool',
    ];

    /** @return array<int, array<string,mixed>> Ordered stages. */
    public function stages(): array
    {
        return self::db()->table('pipeline_stages')
            ->where('pipeline_id', '=', (int) $this->getKey())
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get();
    }
}
