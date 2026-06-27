<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * An uploaded file (docs/30 File Upload) stored on a configured disk and recorded
 * in the `files` table. Files belong to a workspace and (optionally) a folder; the
 * concrete bytes live under the storage provider's disk while this row holds the
 * relative path, original name, mime, size and a sha-256 checksum. `visibility_id`
 * resolves to the `file_visibility` lookup (private|workspace|public). Tenant-scoped,
 * uuid-keyed, soft-deletable.
 */
final class File extends Model
{
    protected static string $table = 'files';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'user_id', 'folder_id', 'storage_provider_id', 'disk', 'path',
        'original_name', 'mime', 'size', 'checksum', 'visibility_id', 'meta',
    ];

    protected static array $casts = [
        'user_id'             => 'int',
        'folder_id'           => 'int',
        'storage_provider_id' => 'int',
        'size'                => 'int',
        'visibility_id'       => 'int',
    ];
}
