<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\ValueObject;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Kernel\Domain\UserId;
use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;

/**
 * The immutable descriptive context an {@see \Nizam\Runtime\Execution\Domain\Execution} is created with.
 *
 * Metadata answers "on whose behalf, in what department, under which manager, and for what intent"
 * an execution runs, plus a free-form label map for routing and reporting. The tenant is always
 * present (every execution is tenant-scoped); the user, department reference, and manager reference
 * are optional because some executions are system-initiated or resolved later during planning. The
 * intent reference names the requested capability. Being a value object, it is compared by value.
 */
final class ExecutionMetadata implements ValueObject
{
    /** @var array<string, string> */
    private readonly array $labels;

    /**
     * @param TenantId              $tenantId      The owning tenant (always present).
     * @param UserId|null           $userId        The user on whose behalf the execution runs, if any.
     * @param string|null           $departmentRef The department the work is routed to, if resolved.
     * @param string|null           $managerRef    The manager plugin overseeing the work, if resolved.
     * @param string                $intentRef     The requested capability/intent identifier.
     * @param array<string, string> $labels        Free-form routing/reporting labels.
     */
    public function __construct(
        private readonly TenantId $tenantId,
        private readonly ?UserId $userId,
        private readonly ?string $departmentRef,
        private readonly ?string $managerRef,
        private readonly string $intentRef,
        array $labels = [],
    ) {
        Assert::notEmpty($intentRef, 'An execution must record the intent it fulfils.');
        if ($departmentRef !== null) {
            Assert::notEmpty($departmentRef, 'departmentRef, when provided, must not be empty.');
        }
        if ($managerRef !== null) {
            Assert::notEmpty($managerRef, 'managerRef, when provided, must not be empty.');
        }

        $normalized = [];
        foreach ($labels as $key => $value) {
            Assert::that(is_string($key) && $key !== '', 'Label keys must be non-empty strings.');
            Assert::that(is_string($value), 'Label values must be strings.');
            $normalized[$key] = $value;
        }
        ksort($normalized);
        $this->labels = $normalized;
    }

    /**
     * Return a copy with the manager reference set (used once planning resolves the manager).
     */
    public function withManagerRef(string $managerRef): self
    {
        return new self(
            $this->tenantId,
            $this->userId,
            $this->departmentRef,
            $managerRef,
            $this->intentRef,
            $this->labels,
        );
    }

    /**
     * Return a copy with the department reference set.
     */
    public function withDepartmentRef(string $departmentRef): self
    {
        return new self(
            $this->tenantId,
            $this->userId,
            $departmentRef,
            $this->managerRef,
            $this->intentRef,
            $this->labels,
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
     * The user on whose behalf the execution runs, if any.
     */
    public function userId(): ?UserId
    {
        return $this->userId;
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
     * The requested capability/intent identifier.
     */
    public function intentRef(): string
    {
        return $this->intentRef;
    }

    /**
     * The free-form routing/reporting labels, ordered by key.
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
            && $other->tenantId->equals($this->tenantId)
            && $this->userEquals($other->userId)
            && $other->departmentRef === $this->departmentRef
            && $other->managerRef === $this->managerRef
            && $other->intentRef === $this->intentRef
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

    /**
     * A scalar-only representation suitable for JSON persistence and read models.
     *
     * @return array{
     *     tenantId: string,
     *     userId: string|null,
     *     departmentRef: string|null,
     *     managerRef: string|null,
     *     intentRef: string,
     *     labels: array<string, string>
     * }
     */
    public function toArray(): array
    {
        return [
            'tenantId' => $this->tenantId->toString(),
            'userId' => $this->userId?->toString(),
            'departmentRef' => $this->departmentRef,
            'managerRef' => $this->managerRef,
            'intentRef' => $this->intentRef,
            'labels' => $this->labels,
        ];
    }
}
