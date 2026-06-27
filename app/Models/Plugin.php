<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A registered plugin package (docs/51 Plugin SDK). Platform-level (no
 * `workspace_id`, like `ai_providers`/`system_modules`): the package is installed
 * once; per-tenant enablement lives in `workspace_plugins`. `status` is a
 * code-validated VARCHAR (installed|enabled|disabled|uninstalled).
 */
final class Plugin extends Model
{
    protected static string $table = 'plugins';
    protected static bool $tenantScoped = false;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'key', 'name', 'author', 'version', 'description', 'status',
        'manifest', 'dependencies', 'permissions', 'license',
        'min_platform_version', 'installed_at', 'enabled_at',
    ];

    protected static array $casts = [
        'manifest'     => 'array',
        'dependencies' => 'array',
        'permissions'  => 'array',
    ];
}
