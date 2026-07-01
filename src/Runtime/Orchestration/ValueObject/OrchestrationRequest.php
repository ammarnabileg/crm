<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\ValueObject;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Kernel\Domain\UserId;
use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;

/**
 * The immutable request the {@see \Nizam\Runtime\Orchestration\MasterOrchestrator} accepts as the sole
 * entry point into the Runtime.
 *
 * Everything a caller (e.g. the Bayan conversational layer) needs to ask the platform to do work is
 * expressed here: the owning tenant, the optional acting user, the intent (capability) to fulfil, an
 * opaque payload the manager/workers interpret, and an optional department hint that biases routing to
 * a Manager plugin. The orchestrator validates and enriches this into an
 * {@see \Nizam\Runtime\Execution\Application\ValueObject\ExecutionRequest} — callers never build an
 * execution request themselves. Being a value object it is immutable and self-validating.
 */
final class OrchestrationRequest implements ValueObject
{
    /** @var array<string, mixed> */
    private readonly array $payload;

    /**
     * @param TenantId             $tenantId      The owning tenant (always present).
     * @param UserId|null          $userId        The user on whose behalf the work runs, if any.
     * @param string               $intentRef     The requested capability/intent identifier.
     * @param array<string, mixed> $payload       The opaque request payload the manager interprets.
     * @param string|null          $departmentRef The department the work should be routed to, if hinted.
     */
    public function __construct(
        private readonly TenantId $tenantId,
        private readonly ?UserId $userId,
        private readonly string $intentRef,
        array $payload,
        private readonly ?string $departmentRef = null,
    ) {
        Assert::notEmpty($intentRef, 'An orchestration request must name the intent it fulfils.');
        if ($departmentRef !== null) {
            Assert::notEmpty($departmentRef, 'departmentRef, when provided, must not be empty.');
        }

        $normalized = [];
        foreach ($payload as $key => $value) {
            Assert::that(is_string($key) && $key !== '', 'Payload keys must be non-empty strings.');
            $normalized[$key] = $value;
        }
        $this->payload = $normalized;
    }

    /**
     * Build a request from primitive inputs, rehydrating the tenant and optional user identifiers.
     *
     * @param string               $tenantId      The owning tenant's UUID.
     * @param string|null          $userId        The acting user's UUID, if any.
     * @param string               $intentRef     The requested capability/intent identifier.
     * @param array<string, mixed> $payload       The opaque request payload.
     * @param string|null          $departmentRef The department reference, if hinted.
     */
    public static function create(
        string $tenantId,
        ?string $userId,
        string $intentRef,
        array $payload,
        ?string $departmentRef = null,
    ): self {
        return new self(
            tenantId: TenantId::fromString($tenantId),
            userId: $userId !== null ? UserId::fromString($userId) : null,
            intentRef: $intentRef,
            payload: $payload,
            departmentRef: $departmentRef,
        );
    }

    /**
     * The owning tenant.
     */
    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    /**
     * The user on whose behalf the work runs, if any.
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
     * The department the work should be routed to, if hinted.
     */
    public function departmentRef(): ?string
    {
        return $this->departmentRef;
    }

    /**
     * Structural equality across every attribute.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->tenantId->equals($this->tenantId)
            && $this->userEquals($other->userId)
            && $other->intentRef === $this->intentRef
            && $other->payload === $this->payload
            && $other->departmentRef === $this->departmentRef;
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
