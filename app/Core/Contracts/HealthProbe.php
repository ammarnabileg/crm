<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

use HaHireAI\Core\Health\HealthResult;

/**
 * A single pluggable health probe. Probes self-register with the
 * HealthChecker. See docs/HEALTH_CHECK_SYSTEM.md.
 */
interface HealthProbe
{
    /** Stable identifier, e.g. "php.version", "database". */
    public function name(): string;

    /** "critical" | "warning" | "info" — how a failure is weighted. */
    public function severity(): string;

    /** Run the check and return its result. MUST NOT throw. */
    public function check(): HealthResult;
}
