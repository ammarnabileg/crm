<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A stage within a recruitment pipeline (docs/53 ATS): name, color, order, the
 * `application_status_id` it maps to, a `stage_type_id`, and initial/terminal/passed
 * flags. Tenant-scoped, soft-deletable.
 */
final class PipelineStage extends Model
{
    protected static string $table = 'pipeline_stages';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'pipeline_id', 'application_status_id', 'stage_type_id', 'name', 'description',
        'color', 'sort_order', 'is_initial', 'is_terminal', 'is_passed', 'auto_advance',
    ];

    protected static array $casts = [
        'sort_order'   => 'int',
        'is_initial'   => 'bool',
        'is_terminal'  => 'bool',
        'is_passed'    => 'bool',
        'auto_advance' => 'bool',
    ];
}
