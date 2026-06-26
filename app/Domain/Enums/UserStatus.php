<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * Account status for a user. Mirrors the `users.status` column ENUM and is used
 * to type-check status values at layer boundaries (docs/47 EAS-4/Validation).
 */
enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Active    => 'Active',
            self::Suspended => 'Suspended',
            self::Pending   => 'Pending',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
