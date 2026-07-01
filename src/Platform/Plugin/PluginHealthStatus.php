<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin;

use DateTimeImmutable;
use Nizam\Kernel\Domain\ValueObject;

/**
 * The immutable outcome of a plugin health check.
 *
 * A health status pairs a {@see HealthLevel} with a human-readable message and the instant the check
 * was taken. It is produced by a plugin's {@see Port\HealthCheck} adapter, stored on the
 * {@see RegisteredPlugin} as its last-known health, and surfaced in the marketplace. Being immutable
 * and self-contained, it can be persisted verbatim and compared for equality.
 */
final class PluginHealthStatus implements ValueObject
{
    /**
     * @param HealthLevel       $level     The health level reported.
     * @param string            $message   A human-readable explanation of the level.
     * @param DateTimeImmutable $checkedAt When the check was taken.
     */
    private function __construct(
        private readonly HealthLevel $level,
        private readonly string $message,
        private readonly DateTimeImmutable $checkedAt,
    ) {
    }

    /**
     * A healthy status.
     */
    public static function healthy(DateTimeImmutable $checkedAt, string $message = 'OK'): self
    {
        return new self(HealthLevel::Healthy, $message, $checkedAt);
    }

    /**
     * A degraded status.
     */
    public static function degraded(DateTimeImmutable $checkedAt, string $message): self
    {
        return new self(HealthLevel::Degraded, $message, $checkedAt);
    }

    /**
     * An unhealthy status.
     */
    public static function unhealthy(DateTimeImmutable $checkedAt, string $message): self
    {
        return new self(HealthLevel::Unhealthy, $message, $checkedAt);
    }

    /**
     * Construct a status at a given level.
     */
    public static function at(HealthLevel $level, DateTimeImmutable $checkedAt, string $message): self
    {
        return new self($level, $message, $checkedAt);
    }

    /**
     * The health level reported.
     */
    public function level(): HealthLevel
    {
        return $this->level;
    }

    /**
     * The human-readable explanation.
     */
    public function message(): string
    {
        return $this->message;
    }

    /**
     * When the check was taken.
     */
    public function checkedAt(): DateTimeImmutable
    {
        return $this->checkedAt;
    }

    /**
     * Whether the reported level is usable (healthy or degraded).
     */
    public function isUsable(): bool
    {
        return $this->level->isUsable();
    }

    /**
     * Value equality across level, message and check instant.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $this->level === $other->level
            && $this->message === $other->message
            && $this->checkedAt->getTimestamp() === $other->checkedAt->getTimestamp();
    }
}
