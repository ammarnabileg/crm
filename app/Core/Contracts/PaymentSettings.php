<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * Platform-wide payment switch the System Owner controls. When payments are
 * disabled the platform runs in free mode: plans are granted free for a limited
 * period and nothing is ever charged. Billing depends on this contract, never on
 * Platform\Application\PlatformSettings (ARCHITECTURE.md §4). Bound at boot.
 */
interface PaymentSettings
{
    /** True if the platform is accepting payment (false = free mode for everyone). */
    public function paymentsEnabled(): bool;
}
