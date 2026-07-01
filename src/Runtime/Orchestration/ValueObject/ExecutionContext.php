<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\ValueObject;

use DateTimeImmutable;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Kernel\Domain\UserId;
use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Plugin\PermissionSet;
use Nizam\Platform\Support\Assert;
use Nizam\Runtime\Execution\Domain\ExecutionId;

/**
 * The request-scoped, read-only context every orchestration collaborator runs within.
 *
 * The {@see \Nizam\Runtime\Orchestration\MasterOrchestrator} builds one context per request and threads
 * it through the {@see \Nizam\Runtime\Orchestration\WorkerCoordinator}, managers, and workers so they
 * all see the same tenant, the same acting user, the same {@see PermissionSet} the tenant granted, a
 * shared correlation id for tracing, and the deadline the work must finish by. It carries identity and
 * authority only — never behavior and never mutable state — so it can be passed freely without a
 * collaborator being able to alter the run. The permission set is what the coordinator checks before it
 * dispatches a worker, keeping authorization at the single Runtime seam.
 */
final class ExecutionContext implements ValueObject
{
    /**
     * @param ExecutionId       $executionId        The execution this context belongs to.
     * @param TenantId          $tenantId           The owning tenant.
     * @param UserId|null       $userId             The acting user, if any.
     * @param PermissionSet     $grantedPermissions The permissions the tenant granted for this run.
     * @param string            $correlationId      A stable id correlating logs/events across the run.
     * @param DateTimeImmutable $deadline           The instant by which the work must finish.
     */
    public function __construct(
        private readonly ExecutionId $executionId,
        private readonly TenantId $tenantId,
        private readonly ?UserId $userId,
        private readonly PermissionSet $grantedPermissions,
        private readonly string $correlationId,
        private readonly DateTimeImmutable $deadline,
    ) {
        Assert::notEmpty($correlationId, 'An execution context must carry a non-empty correlation id.');
    }

    /**
     * The execution this context belongs to.
     */
    public function executionId(): ExecutionId
    {
        return $this->executionId;
    }

    /**
     * The owning tenant.
     */
    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    /**
     * The acting user, if any.
     */
    public function userId(): ?UserId
    {
        return $this->userId;
    }

    /**
     * The permissions the tenant granted for this run.
     */
    public function grantedPermissions(): PermissionSet
    {
        return $this->grantedPermissions;
    }

    /**
     * The stable id correlating logs/events across the run.
     */
    public function correlationId(): string
    {
        return $this->correlationId;
    }

    /**
     * The instant by which the work must finish.
     */
    public function deadline(): DateTimeImmutable
    {
        return $this->deadline;
    }

    /**
     * Whether the deadline has passed relative to the given instant.
     */
    public function hasExpired(DateTimeImmutable $now): bool
    {
        return $now > $this->deadline;
    }

    /**
     * Structural equality across every attribute.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->executionId->equals($this->executionId)
            && $other->tenantId->equals($this->tenantId)
            && $this->userEquals($other->userId)
            && $other->grantedPermissions->equals($this->grantedPermissions)
            && $other->correlationId === $this->correlationId
            && $other->deadline == $this->deadline;
    }

    /**
     * Compare the optional user id by value, treating two nulls as equal.
     */
    private function userEquals(?UserId $other): bool
    {
        if ($this->userId === null || $other === null) {
            return $this->userId === null && $other === null;
        }

        return $this->userId->equals($other);
    }
}
