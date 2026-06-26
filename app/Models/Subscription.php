<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Subscription extends Model
{
    protected static string $table = 'subscriptions';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'workspace_id', 'plan_id', 'status', 'amount', 'currency',
        'trial_ends_at', 'starts_at', 'ends_at', 'canceled_at',
    ];

    protected static array $casts = ['amount' => 'float'];

    public function plan(): ?Plan
    {
        $id = $this->attributes['plan_id'] ?? null;

        return $id ? Plan::find((int) $id) : null;
    }

    public function isActive(): bool
    {
        return in_array($this->attributes['status'] ?? '', ['trialing', 'active'], true);
    }

    public function onTrial(): bool
    {
        return ($this->attributes['status'] ?? '') === 'trialing';
    }
}
