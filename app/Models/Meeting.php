<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A scheduled meeting (docs/53 ATS Scheduler) — typically an interview slot linked
 * to an `interview_id` on a `schedule`. Tenant-scoped, soft-deletable.
 */
final class Meeting extends Model
{
    protected static string $table = 'meetings';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'schedule_id', 'interview_id', 'organizer_user_id', 'title', 'description',
        'type_id', 'status_id', 'location_type_id', 'location', 'meeting_url',
        'meeting_provider_id', 'starts_at', 'ends_at', 'timezone_id', 'all_day', 'created_by',
    ];

    protected static array $casts = [
        'all_day' => 'bool',
    ];
}
