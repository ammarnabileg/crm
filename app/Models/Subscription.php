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
        'workspace_id', 'plan_id', 'subscription_status_id', 'amount', 'currency',
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
        $statusId = (int) ($this->attributes['subscription_status_id'] ?? 0);

        return in_array($statusId, [
            (int) status_id('subscription_statuses', 'trialing'),
            (int) status_id('subscription_statuses', 'active'),
        ], true);
    }

    public function onTrial(): bool
    {
        return (int) ($this->attributes['subscription_status_id'] ?? 0) === status_id('subscription_statuses', 'trialing');
    }

    /**
     * The current status key (e.g. `active`), resolved from the status table.
     */
    public function statusKey(): string
    {
        $id = (int) ($this->attributes['subscription_status_id'] ?? 0);
        if ($id === 0) {
            return '';
        }

        $row = static::db()->table('subscription_statuses')->where('id', '=', $id)->first();

        return (string) ($row['key'] ?? '');
    }
}
