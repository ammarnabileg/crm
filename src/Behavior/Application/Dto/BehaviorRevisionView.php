<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Dto;

use Nizam\Behavior\Domain\BehaviorProfileRevision;

/**
 * A flat, read-only projection of one {@see BehaviorProfileRevision} for callers outside the domain.
 *
 * Every field is a scalar or a plain array so the view can be serialized to JSON, rendered by the
 * (not-yet-built) Interface layer, or returned across a bus without leaking domain objects. It
 * captures a single point in a profile's append-only history: the version, the traits that were in
 * force, the audit trail (change-log, approver, instant), and the approved evidence relied upon.
 */
final class BehaviorRevisionView
{
    /**
     * @param int                              $version    The version number this revision represents.
     * @param array<string, int|string>        $traits     The traits in force at this version, as scalars.
     * @param array<string, mixed>             $changeLog  The audit record for this version, as scalars/arrays.
     * @param string                           $approvedBy Identity of the approver who authorized this version.
     * @param string                           $approvedAt When this version was approved (ISO-8601).
     * @param list<array<string, mixed>>       $evidence   The approved evidence relied upon, as scalar arrays.
     * @param bool                             $isRollback Whether this revision records a rollback.
     * @param int|null                         $rollbackToVersion The version restored, when this is a rollback.
     */
    public function __construct(
        public readonly int $version,
        public readonly array $traits,
        public readonly array $changeLog,
        public readonly string $approvedBy,
        public readonly string $approvedAt,
        public readonly array $evidence,
        public readonly bool $isRollback,
        public readonly ?int $rollbackToVersion,
    ) {
    }

    /**
     * Project a domain revision into its read-only view.
     */
    public static function fromDomain(BehaviorProfileRevision $revision): self
    {
        $revisionData = $revision->toArray();
        $changeLog = $revision->changeLog();

        return new self(
            version: $revisionData['version'],
            traits: $revisionData['traits'],
            changeLog: $revisionData['changeLog'],
            approvedBy: $revisionData['approvedBy'],
            approvedAt: $revisionData['approvedAt'],
            evidence: $revisionData['evidence'],
            isRollback: $changeLog->isRollback(),
            rollbackToVersion: $changeLog->rollbackToVersion(),
        );
    }

    /**
     * A scalar-only representation suitable for JSON serialization and transport.
     *
     * @return array{
     *     version: int,
     *     traits: array<string, int|string>,
     *     changeLog: array<string, mixed>,
     *     approvedBy: string,
     *     approvedAt: string,
     *     evidence: list<array<string, mixed>>,
     *     isRollback: bool,
     *     rollbackToVersion: int|null
     * }
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'traits' => $this->traits,
            'changeLog' => $this->changeLog,
            'approvedBy' => $this->approvedBy,
            'approvedAt' => $this->approvedAt,
            'evidence' => $this->evidence,
            'isRollback' => $this->isRollback,
            'rollbackToVersion' => $this->rollbackToVersion,
        ];
    }
}
