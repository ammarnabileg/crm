<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A job opening (docs/53 ATS). Drives the recruitment pipeline; status flows
 * draft→open→paused→closed→archived via `job_statuses`. Has one pipeline
 * (`pipeline_id`) of stages applications move through. Tenant-scoped, soft-deletable.
 */
final class Job extends Model
{
    protected static string $table = 'jobs';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'job_status_id', 'pipeline_id', 'department_id', 'employment_type_id', 'experience_level_id',
        'title', 'slug', 'description', 'summary', 'openings', 'is_remote',
        'salary_min', 'salary_max', 'currency_id', 'salary_period_id', 'is_salary_public',
        'meta', 'created_by', 'updated_by', 'published_at', 'closed_at',
    ];

    protected static array $casts = [
        'meta'             => 'array',
        'openings'         => 'int',
        'is_remote'        => 'bool',
        'is_salary_public' => 'bool',
        'salary_min'       => 'float',
        'salary_max'       => 'float',
    ];

    public function statusKey(): ?string
    {
        return $this->job_status_id !== null
            ? (string) self::db()->table('job_statuses')->where('id', '=', (int) $this->job_status_id)->value('key')
            : null;
    }
}
