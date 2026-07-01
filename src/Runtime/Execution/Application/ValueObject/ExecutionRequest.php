<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application\ValueObject;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Kernel\Domain\UserId;
use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\ValueObject\ExecutionMetadata;
use Nizam\Runtime\Execution\Domain\ValueObject\RetryPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\TimeoutPolicy;

/**
 * The immutable input the {@see \Nizam\Runtime\Execution\Application\ExecutionEngine} runs one execution from.
 *
 * A request names the tenant an execution runs for, the optional user on whose behalf it runs, the
 * intent (capability) it fulfils, an opaque payload the manager/workers interpret, and the optional
 * department and manager references resolved upstream by the Master Orchestrator. It may carry an
 * explicit {@see RetryPolicy} and {@see TimeoutPolicy}; when omitted, sensible defaults are used. It
 * also carries the {@see ExecutionId} that identifies the run — the engine's idempotency key — which
 * defaults to a fresh identity so the same request object always drives one deterministic execution.
 * Being a value object, it holds no behavior beyond self-validation and projection helpers.
 */
final class ExecutionRequest implements ValueObject
{
    /** @var array<string, mixed> */
    private readonly array $payload;

    /**
     * @param ExecutionId          $executionId   The execution identity / idempotency key.
     * @param TenantId             $tenantId      The owning tenant (always present).
     * @param UserId|null          $userId        The user on whose behalf the execution runs, if any.
     * @param string               $intentRef     The requested capability/intent identifier.
     * @param array<string, mixed> $payload       The opaque request payload the manager interprets.
     * @param string|null          $departmentRef The department the work is routed to, if resolved.
     * @param string|null          $managerRef    The manager plugin overseeing the work, if resolved.
     * @param RetryPolicy          $retryPolicy   The retry rules to apply.
     * @param TimeoutPolicy        $timeoutPolicy The time budget to enforce.
     * @param array<string, string> $labels       Free-form routing/reporting labels.
     */
    public function __construct(
        private readonly ExecutionId $executionId,
        private readonly TenantId $tenantId,
        private readonly ?UserId $userId,
        private readonly string $intentRef,
        array $payload,
        private readonly ?string $departmentRef,
        private readonly ?string $managerRef,
        private readonly RetryPolicy $retryPolicy,
        private readonly TimeoutPolicy $timeoutPolicy,
        private readonly array $labels = [],
    ) {
        Assert::notEmpty($intentRef, 'An execution request must name the intent it fulfils.');
        if ($departmentRef !== null) {
            Assert::notEmpty($departmentRef, 'departmentRef, when provided, must not be empty.');
        }
        if ($managerRef !== null) {
            Assert::notEmpty($managerRef, 'managerRef, when provided, must not be empty.');
        }

        $normalized = [];
        foreach ($payload as $key => $value) {
            Assert::that(is_string($key) && $key !== '', 'Payload keys must be non-empty strings.');
            $normalized[$key] = $value;
        }
        $this->payload = $normalized;
    }

    /**
     * Build a request from primitive inputs, minting a fresh execution id and defaulting the policies.
     *
     * @param string                 $tenantId      The owning tenant's UUID.
     * @param string|null            $userId        The acting user's UUID, if any.
     * @param string                 $intentRef     The requested capability/intent identifier.
     * @param array<string, mixed>   $payload       The opaque request payload.
     * @param string|null            $departmentRef The department reference, if known.
     * @param string|null            $managerRef    The manager plugin reference, if known.
     * @param RetryPolicy|null       $retryPolicy   The retry rules, or null for the default policy.
     * @param TimeoutPolicy|null     $timeoutPolicy The time budget, or null for the default budget.
     * @param array<string, string>  $labels        Free-form routing/reporting labels.
     */
    public static function create(
        string $tenantId,
        ?string $userId,
        string $intentRef,
        array $payload,
        ?string $departmentRef = null,
        ?string $managerRef = null,
        ?RetryPolicy $retryPolicy = null,
        ?TimeoutPolicy $timeoutPolicy = null,
        array $labels = [],
    ): self {
        return new self(
            executionId: ExecutionId::generate(),
            tenantId: TenantId::fromString($tenantId),
            userId: $userId !== null ? UserId::fromString($userId) : null,
            intentRef: $intentRef,
            payload: $payload,
            departmentRef: $departmentRef,
            managerRef: $managerRef,
            retryPolicy: $retryPolicy ?? RetryPolicy::default(),
            timeoutPolicy: $timeoutPolicy ?? TimeoutPolicy::default(),
            labels: $labels,
        );
    }

    /**
     * The metadata an {@see \Nizam\Runtime\Execution\Domain\Execution} should be started with.
     */
    public function toMetadata(): ExecutionMetadata
    {
        return new ExecutionMetadata(
            $this->tenantId,
            $this->userId,
            $this->departmentRef,
            $this->managerRef,
            $this->intentRef,
            $this->labels,
        );
    }

    /**
     * The execution identity / idempotency key.
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
     * The user on whose behalf the execution runs, if any.
     */
    public function userId(): ?UserId
    {
        return $this->userId;
    }

    /**
     * The requested capability/intent identifier.
     */
    public function intentRef(): string
    {
        return $this->intentRef;
    }

    /**
     * The opaque request payload the manager interprets.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * The department the work is routed to, if resolved.
     */
    public function departmentRef(): ?string
    {
        return $this->departmentRef;
    }

    /**
     * The manager plugin overseeing the work, if resolved.
     */
    public function managerRef(): ?string
    {
        return $this->managerRef;
    }

    /**
     * The retry rules to apply.
     */
    public function retryPolicy(): RetryPolicy
    {
        return $this->retryPolicy;
    }

    /**
     * The time budget to enforce.
     */
    public function timeoutPolicy(): TimeoutPolicy
    {
        return $this->timeoutPolicy;
    }

    /**
     * The free-form routing/reporting labels.
     *
     * @return array<string, string>
     */
    public function labels(): array
    {
        return $this->labels;
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
            && $other->intentRef === $this->intentRef
            && $other->payload === $this->payload
            && $other->departmentRef === $this->departmentRef
            && $other->managerRef === $this->managerRef
            && $other->retryPolicy->equals($this->retryPolicy)
            && $other->timeoutPolicy->equals($this->timeoutPolicy)
            && $other->labels === $this->labels;
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
