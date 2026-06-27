<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A recruiter task (docs/53 ATS Task Management) — a to-do linked polymorphically to
 * a job/candidate/interview/etc. via related_type/related_id, assignable to a user.
 * Status is a code-validated VARCHAR (open|in_progress|done|canceled). Tenant-scoped,
 * soft-deletable. Distinct from `scheduled_tasks` (the CRON scheduler).
 */
final class Task extends Model
{
    protected static string $table = 'tasks';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'title', 'description', 'status', 'priority', 'due_at', 'assignee_id',
        'related_type', 'related_id', 'created_by', 'completed_at',
    ];
}
