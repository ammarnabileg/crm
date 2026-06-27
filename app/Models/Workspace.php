<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A tenant. Workspaces are stored in a global table (they cannot scope to
 * themselves) but access is always mediated through memberships and roles.
 */
final class Workspace extends Model
{
    protected static string $table = 'workspaces';
    protected static bool $tenantScoped = false;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'name', 'slug', 'owner_id', 'logo', 'locale', 'timezone', 'workspace_status_id', 'settings',
    ];

    protected static array $casts = ['settings' => 'array'];

    public function owner(): ?User
    {
        $id = $this->attributes['owner_id'] ?? null;

        return $id ? User::find((int) $id) : null;
    }

    public function isActive(): bool
    {
        $statusId = (int) ($this->attributes['workspace_status_id'] ?? 0);

        return in_array($statusId, [
            (int) status_id('workspace_statuses', 'active'),
            (int) status_id('workspace_statuses', 'trial'),
        ], true);
    }

    public function activeSubscription(): ?Subscription
    {
        $row = Subscription::withoutTenantScope()
            ->where('workspace_id', '=', $this->getKey())
            ->whereIn('subscription_status_id', [
                (int) status_id('subscription_statuses', 'trialing'),
                (int) status_id('subscription_statuses', 'active'),
            ])
            ->latest('created_at')
            ->first();

        return $row ? Subscription::hydrate($row) : null;
    }

    /** The current lifecycle status key (e.g. `active`), resolved from the status table. */
    public function statusKey(): string
    {
        $id = (int) ($this->attributes['workspace_status_id'] ?? 0);
        if ($id === 0) {
            return '';
        }

        $row = static::db()->table('workspace_statuses')->where('id', '=', $id)->first();

        return (string) ($row['key'] ?? '');
    }

    public function membersCount(): int
    {
        return Membership::withoutTenantScope()
            ->where('workspace_id', '=', $this->getKey())
            ->where('membership_status_id', '=', lookup_id('membership_status', 'active'))
            ->count();
    }

    public static function uniqueSlug(string $name): string
    {
        $base = slugify($name);
        $slug = $base;
        $i = 1;

        while (static::withoutTenantScope()->where('slug', '=', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }
}
