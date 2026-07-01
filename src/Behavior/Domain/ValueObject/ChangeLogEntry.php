<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\ValueObject;

use DateTimeImmutable;
use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;

/**
 * An immutable audit record describing one versioned change to a behavior profile.
 *
 * Every revision of a {@see \Nizam\Behavior\Domain\BehaviorProfile} carries exactly one change-log
 * entry: which version it produced, when and by whom, a human summary, the per-trait diff that was
 * applied, the stated business impact, and — for a rollback — the prior version that was restored.
 * Together these entries form the profile's transparent, auditable history.
 */
final class ChangeLogEntry implements ValueObject
{
    /**
     * @param int                                          $version          The version this change produced (>= 1).
     * @param DateTimeImmutable                            $changedAt        When the change was applied.
     * @param string                                       $changedBy        Identity of the approver who applied it.
     * @param string                                       $summary          Human-readable summary of the change.
     * @param array<string, array{from: string, to: string}> $traitsDiff     Per-trait diff (see {@see BehaviorTraits::diff()}).
     * @param string                                       $businessImpact   Stated business impact of the change.
     * @param int|null                                     $rollbackToVersion Version restored, when this entry is a rollback.
     */
    public function __construct(
        private readonly int $version,
        private readonly DateTimeImmutable $changedAt,
        private readonly string $changedBy,
        private readonly string $summary,
        private readonly array $traitsDiff,
        private readonly string $businessImpact,
        private readonly ?int $rollbackToVersion = null,
    ) {
        Assert::positive($version, 'ChangeLogEntry version must be a positive integer.');
        Assert::notEmpty($changedBy, 'ChangeLogEntry changedBy must not be empty.');
        Assert::notEmpty($summary, 'ChangeLogEntry summary must not be empty.');
        Assert::notEmpty($businessImpact, 'ChangeLogEntry businessImpact must not be empty.');

        if ($rollbackToVersion !== null) {
            Assert::positive($rollbackToVersion, 'ChangeLogEntry rollbackToVersion must be a positive integer.');
            Assert::that(
                $rollbackToVersion < $version,
                'ChangeLogEntry rollbackToVersion must reference an earlier version than the one it produces.',
            );
        }
    }

    /**
     * The version this change produced.
     */
    public function version(): int
    {
        return $this->version;
    }

    /**
     * When the change was applied.
     */
    public function changedAt(): DateTimeImmutable
    {
        return $this->changedAt;
    }

    /**
     * The identity of the approver who applied the change.
     */
    public function changedBy(): string
    {
        return $this->changedBy;
    }

    /**
     * The human-readable summary of the change.
     */
    public function summary(): string
    {
        return $this->summary;
    }

    /**
     * The per-trait diff applied by this change.
     *
     * @return array<string, array{from: string, to: string}>
     */
    public function traitsDiff(): array
    {
        return $this->traitsDiff;
    }

    /**
     * The stated business impact of the change.
     */
    public function businessImpact(): string
    {
        return $this->businessImpact;
    }

    /**
     * The version restored, when this entry records a rollback; otherwise null.
     */
    public function rollbackToVersion(): ?int
    {
        return $this->rollbackToVersion;
    }

    /**
     * Whether this entry records a rollback to an earlier version.
     */
    public function isRollback(): bool
    {
        return $this->rollbackToVersion !== null;
    }

    /**
     * Structural equality across every attribute.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->version === $this->version
            && $other->changedAt->getTimestamp() === $this->changedAt->getTimestamp()
            && $other->changedBy === $this->changedBy
            && $other->summary === $this->summary
            && $other->traitsDiff === $this->traitsDiff
            && $other->businessImpact === $this->businessImpact
            && $other->rollbackToVersion === $this->rollbackToVersion;
    }

    /**
     * A scalar-only representation suitable for JSON persistence and read models.
     *
     * @return array{
     *     version: int,
     *     changedAt: string,
     *     changedBy: string,
     *     summary: string,
     *     traitsDiff: array<string, array{from: string, to: string}>,
     *     businessImpact: string,
     *     rollbackToVersion: int|null
     * }
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'changedAt' => $this->changedAt->format(DateTimeImmutable::ATOM),
            'changedBy' => $this->changedBy,
            'summary' => $this->summary,
            'traitsDiff' => $this->traitsDiff,
            'businessImpact' => $this->businessImpact,
            'rollbackToVersion' => $this->rollbackToVersion,
        ];
    }
}
