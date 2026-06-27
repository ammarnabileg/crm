<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A candidate's application to a job (docs/53 ATS). Moves through the job's pipeline
 * via `current_stage_id`; `application_status_id` mirrors the stage's status. May be
 * assigned to a recruiter/interviewer. Tenant-scoped, soft-deletable.
 */
final class Application extends Model
{
    protected static string $table = 'applications';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'job_id', 'user_id', 'assigned_recruiter_id', 'assigned_interviewer_id',
        'current_stage_id', 'application_status_id', 'source_id', 'resume_file_id',
        'cover_letter', 'score', 'applied_at', 'decided_at',
    ];

    protected static array $casts = [
        'score' => 'float',
    ];

    public function statusKey(): ?string
    {
        return $this->application_status_id !== null
            ? (string) self::db()->table('application_statuses')->where('id', '=', (int) $this->application_status_id)->value('key')
            : null;
    }
}
