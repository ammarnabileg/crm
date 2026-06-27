<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A polymorphic note (docs/53 ATS) attached to any subject via
 * notable_type/notable_id (e.g. an Application or a candidate). `type_id` resolves
 * to the `note_types` lookup (general|interview|internal|system). Tenant-scoped,
 * soft-deletable.
 */
final class Note extends Model
{
    protected static string $table = 'notes';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'notable_type', 'notable_id', 'type_id', 'user_id', 'body',
        'is_pinned', 'is_private',
    ];

    protected static array $casts = [
        'is_pinned'  => 'bool',
        'is_private' => 'bool',
    ];
}
