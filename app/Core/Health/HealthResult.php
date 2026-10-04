<?php

declare(strict_types=1);

namespace HaHireAI\Core\Health;

/** The immutable outcome of one health probe. */
final class HealthResult
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public readonly HealthStatus $status,
        public readonly string $message = '',
        public readonly array $details = [],
    ) {
    }

    public static function ok(string $message = 'OK', array $details = []): self
    {
        return new self(HealthStatus::Healthy, $message, $details);
    }

    public static function degraded(string $message, array $details = []): self
    {
        return new self(HealthStatus::Degraded, $message, $details);
    }

    public static function unhealthy(string $message, array $details = []): self
    {
        return new self(HealthStatus::Unhealthy, $message, $details);
    }

    public function isHealthy(): bool
    {
        return $this->status === HealthStatus::Healthy;
    }
}
