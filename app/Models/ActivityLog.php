<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Audit trail entry. Written explicitly with a company_id (nullable for
 * platform events), so it is not auto tenant-scoped.
 */
final class ActivityLog extends Model
{
    protected static string $table = 'activity_log';
    protected static bool $tenantScoped = false;
    protected static bool $timestamps = false;

    protected static array $fillable = [
        'company_id', 'user_id', 'action', 'subject_type', 'subject_id',
        'description', 'properties', 'ip', 'user_agent', 'created_at',
    ];

    protected static array $casts = ['properties' => 'array'];

    public static function record(
        string $action,
        ?int $companyId = null,
        ?int $userId = null,
        string $description = '',
        array $properties = [],
        ?string $subjectType = null,
        ?int $subjectId = null,
    ): void {
        $request = app()->has('request') ? request() : null;

        static::withoutTenantScope()->insert([
            'company_id'   => $companyId,
            'user_id'      => $userId,
            'action'       => $action,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'description'  => $description !== '' ? $description : null,
            'properties'   => $properties === [] ? null : json_encode($properties, JSON_UNESCAPED_UNICODE),
            'ip'           => $request?->ip(),
            'user_agent'   => $request ? substr($request->userAgent(), 0, 255) : null,
            'created_at'   => now(),
        ]);
    }
}
