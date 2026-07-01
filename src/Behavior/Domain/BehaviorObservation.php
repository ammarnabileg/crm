<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain;

use DateTimeImmutable;
use Nizam\Behavior\Domain\ValueObject\EvidenceReference;
use Nizam\Kernel\Domain\Entity;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Support\Assert;

/**
 * One recorded, role-bound piece of business practice that may inform a behavior profile.
 *
 * An observation ties an {@see EvidenceReference} to a tenant and a role, and tracks whether that
 * evidence has been approved. The engine's core rule is enforced here: only an observation whose
 * {@see self::isApproved()} returns true may drive the evolution of a role's behavior — unapproved
 * practice is never learned from. Approval carries its approver and timestamp for auditability.
 */
final class BehaviorObservation extends Entity
{
    /**
     * @param ObservationId          $id         The observation's identity.
     * @param TenantId               $tenantId   The owning tenant.
     * @param RoleId                 $roleId     The role this observation pertains to.
     * @param EvidenceReference      $evidence   The cited piece of business practice.
     * @param bool                   $approved   Whether the evidence has been approved.
     * @param string|null            $approvedBy Identity of the approver, when approved.
     * @param DateTimeImmutable|null $approvedAt When it was approved, when approved.
     */
    private function __construct(
        ObservationId $id,
        private readonly TenantId $tenantId,
        private readonly RoleId $roleId,
        private readonly EvidenceReference $evidence,
        private readonly bool $approved,
        private readonly ?string $approvedBy,
        private readonly ?DateTimeImmutable $approvedAt,
    ) {
        parent::__construct($id);

        if ($approved) {
            Assert::notEmpty(
                (string) $approvedBy,
                'An approved observation must record who approved it.',
            );
            Assert::notNull(
                $approvedAt,
                'An approved observation must record when it was approved.',
            );
        }
    }

    /**
     * Record an observation that has not yet been approved.
     *
     * @param ObservationId     $id       The observation's identity.
     * @param TenantId          $tenantId The owning tenant.
     * @param RoleId            $roleId   The role this observation pertains to.
     * @param EvidenceReference $evidence The cited piece of business practice.
     */
    public static function record(
        ObservationId $id,
        TenantId $tenantId,
        RoleId $roleId,
        EvidenceReference $evidence,
    ): self {
        return new self($id, $tenantId, $roleId, $evidence, false, null, null);
    }

    /**
     * Reconstitute an already-approved observation (e.g. when loaded from a repository).
     *
     * @param ObservationId     $id         The observation's identity.
     * @param TenantId          $tenantId   The owning tenant.
     * @param RoleId            $roleId     The role this observation pertains to.
     * @param EvidenceReference $evidence   The cited piece of business practice.
     * @param string            $approvedBy Identity of the approver.
     * @param DateTimeImmutable $approvedAt When it was approved.
     */
    public static function approved(
        ObservationId $id,
        TenantId $tenantId,
        RoleId $roleId,
        EvidenceReference $evidence,
        string $approvedBy,
        DateTimeImmutable $approvedAt,
    ): self {
        return new self($id, $tenantId, $roleId, $evidence, true, $approvedBy, $approvedAt);
    }

    /**
     * Return an approved copy of this observation.
     *
     * Observations are compared by identity and their evidence is immutable, so approval yields a
     * new instance carrying the same identity with the approval metadata set.
     *
     * @param string            $approvedBy Identity of the approver.
     * @param DateTimeImmutable $approvedAt When the approval occurred.
     */
    public function approve(string $approvedBy, DateTimeImmutable $approvedAt): self
    {
        return new self(
            $this->observationId(),
            $this->tenantId,
            $this->roleId,
            $this->evidence,
            true,
            $approvedBy,
            $approvedAt,
        );
    }

    /**
     * The observation's identity, narrowed to {@see ObservationId}.
     */
    public function observationId(): ObservationId
    {
        $id = $this->id();
        assert($id instanceof ObservationId);

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
     * The role this observation pertains to.
     */
    public function roleId(): RoleId
    {
        return $this->roleId;
    }

    /**
     * The cited piece of business practice.
     */
    public function evidence(): EvidenceReference
    {
        return $this->evidence;
    }

    /**
     * Whether the evidence has been approved and may therefore drive behavior evolution.
     */
    public function isApproved(): bool
    {
        return $this->approved;
    }

    /**
     * The identity of the approver, when approved; otherwise null.
     */
    public function approvedBy(): ?string
    {
        return $this->approvedBy;
    }

    /**
     * When the observation was approved, when approved; otherwise null.
     */
    public function approvedAt(): ?DateTimeImmutable
    {
        return $this->approvedAt;
    }
}
