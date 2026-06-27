<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Per-tenant plugin enablement (docs/51 Plugin SDK — Marketplace). A workspace turns
 * a registered plugin on/off and stores its settings, independent of other tenants.
 * Tenant-scoped.
 */
final class WorkspacePlugin extends Model
{
    protected static string $table = 'workspace_plugins';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'plugin_id', 'is_enabled', 'settings', 'enabled_at',
    ];

    protected static array $casts = [
        'is_enabled' => 'bool',
        'settings'   => 'array',
    ];
}
