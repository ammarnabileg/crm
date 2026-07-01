<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain;

use DateTimeImmutable;
use Nizam\Behavior\Domain\ValueObject\BehaviorTraits;
use Nizam\Behavior\Domain\ValueObject\ChangeLogEntry;
use Nizam\Behavior\Domain\ValueObject\EvidenceReference;
use Nizam\Platform\Support\Assert;

/**
 * One immutable, versioned snapshot in a behavior profile's append-only history.
 *
 * A {@see BehaviorProfile} is the ordered sequence of its revisions; each revision fixes the traits
 * that were in force at a given version together with the audit trail that produced them — the
 * change-log entry, the approving identity and instant, and the approved evidence relied upon. A
 * revision is created once and never mutated: history is append-only, so a past revision is a
 * permanent record. Its identity within the aggregate is its {@see self::version()}.
 */
final class BehaviorProfileRevision
{
    /**
     * @param int                     $version    The version number this revision represents (>= 1).
     * @param BehaviorTraits          $traits     The traits in force at this version.
     * @param ChangeLogEntry          $changeLog  The audit record describing how this version came to be.
     * @param string                  $approvedBy Identity of the approver who authorized this version.
     * @param DateTimeImmutable       $approvedAt When this version was approved.
     * @param list<EvidenceReference> $evidence   The approved evidence relied upon for this version.
     */
    public function __construct(
        private readonly int $version,
        private readonly BehaviorTraits $traits,
        private readonly ChangeLogEntry $changeLog,
        private readonly string $approvedBy,
        private readonly DateTimeImmutable $approvedAt,
        private readonly array $evidence,
    ) {
        Assert::positive($version, 'A revision version must be a positive integer.');
        Assert::notEmpty($approvedBy, 'A revision must record who approved it.');
        Assert::that(
            $changeLog->version() === $version,
            'A revision change-log entry must carry the same version as the revision.',
        );
        foreach ($evidence as $reference) {
            Assert::that(
                $reference instanceof EvidenceReference,
                'Revision evidence must contain only EvidenceReference instances.',
            );
        }
    }

    /**
     * The version number this revision represents.
     */
    public function version(): int
    {
        return $this->version;
    }

    /**
     * The traits in force at this version.
     */
    public function traits(): BehaviorTraits
    {
        return $this->traits;
    }

    /**
     * The audit record describing how this version came to be.
     */
    public function changeLog(): ChangeLogEntry
    {
        return $this->changeLog;
    }

    /**
     * The identity of the approver who authorized this version.
     */
    public function approvedBy(): string
    {
        return $this->approvedBy;
    }

    /**
     * When this version was approved.
     */
    public function approvedAt(): DateTimeImmutable
    {
        return $this->approvedAt;
    }

    /**
     * The approved evidence relied upon for this version.
     *
     * @return list<EvidenceReference>
     */
    public function evidence(): array
    {
        return $this->evidence;
    }

    /**
     * A scalar-only representation suitable for JSON persistence and read models.
     *
     * @return array{
     *     version: int,
     *     traits: array<string, mixed>,
     *     changeLog: array<string, mixed>,
     *     approvedBy: string,
     *     approvedAt: string,
     *     evidence: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'traits' => $this->traits->toArray(),
            'changeLog' => $this->changeLog->toArray(),
            'approvedBy' => $this->approvedBy,
            'approvedAt' => $this->approvedAt->format(DateTimeImmutable::ATOM),
            'evidence' => array_map(
                static fn (EvidenceReference $reference): array => $reference->toArray(),
                $this->evidence,
            ),
        ];
    }
}
