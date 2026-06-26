<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * Status of a user's membership within a workspace. Mirrors `memberships.status`.
 */
enum MembershipStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active    => 'Active',
            self::Invited   => 'Invited',
            self::Suspended => 'Suspended',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
