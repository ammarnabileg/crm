<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * Lifecycle status of a subscription. Mirrors `subscriptions.status` and the
 * state machine documented in docs/13-Subscription-System.md.
 */
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Expired = 'expired';

    public function isActive(): bool
    {
        return $this === self::Trialing || $this === self::Active;
    }

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Trialing',
            self::Active   => 'Active',
            self::PastDue  => 'Past due',
            self::Canceled => 'Canceled',
            self::Expired  => 'Expired',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
