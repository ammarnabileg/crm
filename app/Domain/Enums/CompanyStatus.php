<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * Lifecycle status for a company (tenant). Mirrors `companies.status`.
 */
enum CompanyStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';
    case Canceled = 'canceled';

    public function isOperational(): bool
    {
        return $this === self::Trial || $this === self::Active;
    }

    public function label(): string
    {
        return match ($this) {
            self::Trial     => 'Trial',
            self::Active    => 'Active',
            self::Suspended => 'Suspended',
            self::Canceled  => 'Canceled',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
