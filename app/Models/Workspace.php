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
        'name', 'slug', 'owner_id', 'logo', 'locale', 'timezone', 'status', 'settings',
    ];

    protected static array $casts = ['settings' => 'array'];

    public function owner(): ?User
    {
        $id = $this->attributes['owner_id'] ?? null;

        return $id ? User::find((int) $id) : null;
    }

    public function isActive(): bool
    {
        return in_array($this->attributes['status'] ?? '', ['active', 'trial'], true);
    }

    public function activeSubscription(): ?Subscription
    {
        $row = Subscription::withoutTenantScope()
            ->where('workspace_id', '=', $this->getKey())
            ->whereIn('status', ['trialing', 'active'])
            ->latest('created_at')
            ->first();

        return $row ? Subscription::hydrate($row) : null;
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
