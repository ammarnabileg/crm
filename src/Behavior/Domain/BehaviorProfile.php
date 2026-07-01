<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain;

use DateTimeImmutable;
use Nizam\Behavior\Domain\Enum\ProfileStatus;
use Nizam\Behavior\Domain\Event\BehaviorProfileActivated;
use Nizam\Behavior\Domain\Event\BehaviorProfileArchived;
use Nizam\Behavior\Domain\Event\BehaviorProfileDrafted;
use Nizam\Behavior\Domain\Event\BehaviorProfileRolledBack;
use Nizam\Behavior\Domain\Event\BehaviorProfileVersionActivated;
use Nizam\Behavior\Domain\Exception\ImmutableRoleBindingException;
use Nizam\Behavior\Domain\Exception\InsufficientEvidenceException;
use Nizam\Behavior\Domain\Exception\InvalidProfileTransitionException;
use Nizam\Behavior\Domain\Exception\NoBehaviorChangeException;
use Nizam\Behavior\Domain\Exception\UnknownRevisionException;
use Nizam\Behavior\Domain\ValueObject\BehaviorTraits;
use Nizam\Behavior\Domain\ValueObject\ChangeLogEntry;
use Nizam\Behavior\Domain\ValueObject\EvidenceReference;
use Nizam\Kernel\Domain\AggregateRoot;
use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Support\Assert;

/**
 * The versioned, explainable, role-bound description of how work is performed.
 *
 * A behavior profile is the platform's model of a role's professional behavior. It owns an
 * append-only sequence of immutable {@see BehaviorProfileRevision}s: the current traits are always
 * those of the latest revision, and every past revision is a permanent audit record. All state
 * changes are gated — a profile only accepts changes while it is {@see ProfileStatus::Draft} or
 * {@see ProfileStatus::Active}, every applied change must actually differ and must carry at least
 * as many approved evidences as the traits require, and a rollback restores an earlier version's
 * traits as a *new* version rather than rewriting history. The profile is bound to its
 * {@see RoleId} at creation and can never be re-bound. Each mutator records a domain event for the
 * application layer to publish after the unit of work commits.
 */
final class BehaviorProfile extends AggregateRoot
{
    /**
     * @param BehaviorProfileId            $id             The profile's identity.
     * @param TenantId                     $tenantId       The owning tenant.
     * @param RoleId                       $roleId         The role this profile is permanently bound to.
     * @param ProfileStatus                $status         The lifecycle status.
     * @param int                          $currentVersion The current (latest) version number.
     * @param BehaviorTraits               $currentTraits  The traits of the current version.
     * @param list<BehaviorProfileRevision> $revisions     The append-only revision history, ordered by version.
     * @param DateTimeImmutable            $createdAt      When the profile was created.
     * @param DateTimeImmutable            $updatedAt      When the profile last changed.
     * @param int                          $version        Optimistic-concurrency version of the aggregate.
     */
    private function __construct(
        BehaviorProfileId $id,
        private readonly TenantId $tenantId,
        private readonly RoleId $roleId,
        private ProfileStatus $status,
        private int $currentVersion,
        private BehaviorTraits $currentTraits,
        private array $revisions,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private int $version,
    ) {
        parent::__construct($id);
    }

    /**
     * Draft a brand-new behavior profile at version 1 for a role.
     *
     * The profile starts in {@see ProfileStatus::Draft} with a single initial revision and records a
     * {@see BehaviorProfileDrafted} event. It does not yet govern behavior until activated.
     *
     * @param BehaviorProfileId $id        The identity to assign.
     * @param TenantId          $tenantId  The owning tenant.
     * @param RoleId            $roleId    The role to bind the profile to (immutable thereafter).
     * @param BehaviorTraits    $initial   The initial traits.
     * @param string            $draftedBy Identity drafting the profile.
     * @param Clock             $clock     Time source.
     */
    public static function draft(
        BehaviorProfileId $id,
        TenantId $tenantId,
        RoleId $roleId,
        BehaviorTraits $initial,
        string $draftedBy,
        Clock $clock,
    ): self {
        Assert::notEmpty($draftedBy, 'A profile must record who drafted it.');

        $now = $clock->now();
        $changeLog = new ChangeLogEntry(
            version: 1,
            changedAt: $now,
            changedBy: $draftedBy,
            summary: 'Initial behavior profile drafted.',
            traitsDiff: [],
            businessImpact: 'Establishes the starting behavior profile for the role.',
            rollbackToVersion: null,
        );
        $revision = new BehaviorProfileRevision(
            version: 1,
            traits: $initial,
            changeLog: $changeLog,
            approvedBy: $draftedBy,
            approvedAt: $now,
            evidence: [],
        );

        $profile = new self(
            id: $id,
            tenantId: $tenantId,
            roleId: $roleId,
            status: ProfileStatus::Draft,
            currentVersion: 1,
            currentTraits: $initial,
            revisions: [$revision],
            createdAt: $now,
            updatedAt: $now,
            version: 0,
        );

        $profile->recordThat(new BehaviorProfileDrafted($id, $tenantId, $roleId, $draftedBy, $now));

        return $profile;
    }

    /**
     * Reconstitute a profile from persisted state without emitting events.
     *
     * Used by repositories when hydrating. Performs the same structural invariants as construction
     * but records no domain event, since no new business fact has occurred.
     *
     * @param list<BehaviorProfileRevision> $revisions The ordered revision history (non-empty).
     */
    public static function reconstitute(
        BehaviorProfileId $id,
        TenantId $tenantId,
        RoleId $roleId,
        ProfileStatus $status,
        int $currentVersion,
        BehaviorTraits $currentTraits,
        array $revisions,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        int $version,
    ): self {
        Assert::positive($currentVersion, 'currentVersion must be a positive integer.');
        Assert::that($revisions !== [], 'A profile must have at least one revision.');
        Assert::that($version >= 0, 'The aggregate version must not be negative.');
        foreach ($revisions as $revision) {
            Assert::that(
                $revision instanceof BehaviorProfileRevision,
                'Profile revisions must contain only BehaviorProfileRevision instances.',
            );
        }

        return new self(
            id: $id,
            tenantId: $tenantId,
            roleId: $roleId,
            status: $status,
            currentVersion: $currentVersion,
            currentTraits: $currentTraits,
            revisions: array_values($revisions),
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            version: $version,
        );
    }

    /**
     * Activate a drafted profile so it begins governing its role.
     *
     * @param string $approvedBy Identity performing the activation.
     * @param Clock  $clock      Time source.
     *
     * @throws InvalidProfileTransitionException When the profile is not in {@see ProfileStatus::Draft}.
     */
    public function activate(string $approvedBy, Clock $clock): void
    {
        Assert::notEmpty($approvedBy, 'Activation must record who approved it.');

        if ($this->status !== ProfileStatus::Draft) {
            throw InvalidProfileTransitionException::forOperation('activate', $this->status);
        }

        $now = $clock->now();
        $this->status = ProfileStatus::Active;
        $this->touch($now);

        $this->recordThat(new BehaviorProfileActivated(
            $this->profileId(),
            $this->tenantId,
            $this->roleId,
            $this->currentVersion,
            $approvedBy,
            $now,
        ));
    }

    /**
     * Apply an approved trait change as a new appended version.
     *
     * Increments the current version, appends an immutable revision carrying the new traits and the
     * supplied audit trail, updates the current traits, and records a
     * {@see BehaviorProfileVersionActivated} event.
     *
     * Guards: the profile must be {@see ProfileStatus::Draft} or {@see ProfileStatus::Active}; the new
     * traits must differ from the current traits; and the count of approved evidence must be at least
     * the current traits' `evidenceRequirements`.
     *
     * @param BehaviorTraits          $newTraits        The traits to adopt.
     * @param ChangeLogEntry          $changeLog        The audit record for the change.
     * @param list<EvidenceReference> $approvedEvidence The approved evidence backing the change.
     * @param string                  $approvedBy       Identity approving the change.
     * @param Clock                   $clock            Time source.
     *
     * @throws InvalidProfileTransitionException When the profile does not accept changes.
     * @throws NoBehaviorChangeException         When the new traits equal the current traits.
     * @throws InsufficientEvidenceException     When too little approved evidence is supplied.
     */
    public function applyApprovedChange(
        BehaviorTraits $newTraits,
        ChangeLogEntry $changeLog,
        array $approvedEvidence,
        string $approvedBy,
        Clock $clock,
    ): void {
        Assert::notEmpty($approvedBy, 'A change must record who approved it.');

        if (!$this->status->acceptsChanges()) {
            throw InvalidProfileTransitionException::forOperation('apply a change to', $this->status);
        }

        if ($this->currentTraits->equals($newTraits)) {
            throw NoBehaviorChangeException::create();
        }

        $required = $this->currentTraits->evidenceRequirements();
        $provided = count($approvedEvidence);
        if ($provided < $required) {
            throw InsufficientEvidenceException::forCounts($provided, $required);
        }

        foreach ($approvedEvidence as $reference) {
            Assert::that(
                $reference instanceof EvidenceReference,
                'approvedEvidence must contain only EvidenceReference instances.',
            );
        }

        $now = $clock->now();
        $newVersion = $this->currentVersion + 1;

        Assert::that(
            $changeLog->version() === $newVersion,
            'The change-log entry version must equal the new profile version.',
        );

        $revision = new BehaviorProfileRevision(
            version: $newVersion,
            traits: $newTraits,
            changeLog: $changeLog,
            approvedBy: $approvedBy,
            approvedAt: $now,
            evidence: array_values($approvedEvidence),
        );

        $this->revisions[] = $revision;
        $this->currentVersion = $newVersion;
        $this->currentTraits = $newTraits;
        $this->touch($now);

        $this->recordThat(new BehaviorProfileVersionActivated(
            $this->profileId(),
            $this->tenantId,
            $this->roleId,
            $newVersion,
            $changeLog->traitsDiff(),
            $approvedBy,
            $now,
        ));
    }

    /**
     * Roll the profile back to the traits of an earlier version, append-only and reversibly.
     *
     * Rather than deleting history, this restores the target version's traits as a brand-new, higher
     * version whose change-log records the rollback source. A subsequent rollback can therefore undo
     * it. Records a {@see BehaviorProfileRolledBack} event.
     *
     * @param int    $version    The earlier version to restore.
     * @param string $approvedBy Identity approving the rollback.
     * @param Clock  $clock      Time source.
     *
     * @throws InvalidProfileTransitionException When the profile does not accept changes.
     * @throws UnknownRevisionException          When no revision has the requested version.
     * @throws NoBehaviorChangeException         When the target traits equal the current traits.
     */
    public function rollbackTo(int $version, string $approvedBy, Clock $clock): void
    {
        Assert::notEmpty($approvedBy, 'A rollback must record who approved it.');

        if (!$this->status->acceptsChanges()) {
            throw InvalidProfileTransitionException::forOperation('roll back', $this->status);
        }

        $target = $this->revisionOfVersion($version);
        if ($target === null) {
            throw UnknownRevisionException::forVersion($version, $this->profileId()->toString());
        }

        $restoredTraits = $target->traits();
        if ($this->currentTraits->equals($restoredTraits)) {
            throw NoBehaviorChangeException::create();
        }

        $now = $clock->now();
        $newVersion = $this->currentVersion + 1;
        $diff = $this->currentTraits->diff($restoredTraits);

        $changeLog = new ChangeLogEntry(
            version: $newVersion,
            changedAt: $now,
            changedBy: $approvedBy,
            summary: sprintf('Rolled back to the behavior of version %d.', $version),
            traitsDiff: $diff,
            businessImpact: sprintf('Restores the previously approved behavior of version %d.', $version),
            rollbackToVersion: $version,
        );

        $revision = new BehaviorProfileRevision(
            version: $newVersion,
            traits: $restoredTraits,
            changeLog: $changeLog,
            approvedBy: $approvedBy,
            approvedAt: $now,
            evidence: $target->evidence(),
        );

        $this->revisions[] = $revision;
        $this->currentVersion = $newVersion;
        $this->currentTraits = $restoredTraits;
        $this->touch($now);

        $this->recordThat(new BehaviorProfileRolledBack(
            $this->profileId(),
            $this->tenantId,
            $this->roleId,
            $version,
            $newVersion,
            $approvedBy,
            $now,
        ));
    }

    /**
     * Archive the profile, retiring it from use while retaining its history.
     *
     * @param string $by    Identity performing the archival.
     * @param Clock  $clock Time source.
     *
     * @throws InvalidProfileTransitionException When the profile is already archived.
     */
    public function archive(string $by, Clock $clock): void
    {
        Assert::notEmpty($by, 'Archival must record who performed it.');

        if ($this->status === ProfileStatus::Archived) {
            throw InvalidProfileTransitionException::forOperation('archive', $this->status);
        }

        $now = $clock->now();
        $this->status = ProfileStatus::Archived;
        $this->touch($now);

        $this->recordThat(new BehaviorProfileArchived(
            $this->profileId(),
            $this->tenantId,
            $this->roleId,
            $by,
            $now,
        ));
    }

    /**
     * Assert that a given role matches this profile's immutable role binding.
     *
     * Applied when an external change (e.g. from a proposal) is about to touch this profile, to
     * enforce that behavior is only ever evolved within a single role.
     *
     * @throws ImmutableRoleBindingException When the supplied role differs from the bound role.
     */
    public function assertBoundToRole(RoleId $roleId): void
    {
        if (!$this->roleId->equals($roleId)) {
            throw ImmutableRoleBindingException::forMismatch(
                $this->roleId->toString(),
                $roleId->toString(),
            );
        }
    }

    /**
     * The profile's identity, narrowed to {@see BehaviorProfileId}.
     */
    public function profileId(): BehaviorProfileId
    {
        $id = $this->id();
        assert($id instanceof BehaviorProfileId);

        return $id;
    }

    /**
     * The owning tenant.
     */
    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    /**
     * The role this profile is permanently bound to.
     */
    public function roleId(): RoleId
    {
        return $this->roleId;
    }

    /**
     * The lifecycle status.
     */
    public function status(): ProfileStatus
    {
        return $this->status;
    }

    /**
     * The current (latest) version number.
     */
    public function currentVersion(): int
    {
        return $this->currentVersion;
    }

    /**
     * The traits of the current version.
     */
    public function currentTraits(): BehaviorTraits
    {
        return $this->currentTraits;
    }

    /**
     * The append-only revision history, ordered by version.
     *
     * @return list<BehaviorProfileRevision>
     */
    public function revisions(): array
    {
        return $this->revisions;
    }

    /**
     * The revision for a specific version, or null when none exists.
     */
    public function revisionOfVersion(int $version): ?BehaviorProfileRevision
    {
        foreach ($this->revisions as $revision) {
            if ($revision->version() === $version) {
                return $revision;
            }
        }

        return null;
    }

    /**
     * When the profile was created.
     */
    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * When the profile last changed.
     */
    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * The optimistic-concurrency version of the aggregate.
     */
    public function version(): int
    {
        return $this->version;
    }

    /**
     * Advance the updated timestamp and the optimistic-concurrency version after a mutation.
     */
    private function touch(DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
        ++$this->version;
    }
}
