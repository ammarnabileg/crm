<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * Platform support contact, shown to members of a suspended workspace. Other
 * modules depend on this contract, not on Platform\Application\PlatformSettings
 * (ARCHITECTURE.md §4). Bound at boot.
 */
interface SupportInfo
{
    /** @return array{email: string, url: string, phone: string, message: string} */
    public function support(): array;
}
