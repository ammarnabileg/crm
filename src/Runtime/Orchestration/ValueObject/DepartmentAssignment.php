<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\ValueObject;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;

/**
 * The routing decision the {@see \Nizam\Runtime\Orchestration\Port\DepartmentResolver} reaches for a
 * request: which department owns the work and which Manager plugin runs it.
 *
 * The orchestrator maps an intent (and any department hint on the request) to a department and the
 * Manager plugin that leads it. This value object binds the resolved {@see self::departmentRef()} to
 * the {@see self::managerRef()} — the plugin name the {@see \Nizam\Runtime\Orchestration\Port\ManagerPluginResolver}
 * then loads. Keeping the two together makes routing auditable: the timeline records both the department
 * the work landed in and the manager that led it. Being a value object it is immutable.
 */
final class DepartmentAssignment implements ValueObject
{
    /**
     * @param string $departmentRef The resolved department reference.
     * @param string $managerRef    The Manager plugin name that leads the department.
     */
    public function __construct(
        private readonly string $departmentRef,
        private readonly string $managerRef,
    ) {
        Assert::notEmpty($departmentRef, 'A department assignment must name the department.');
        Assert::notEmpty($managerRef, 'A department assignment must name the manager plugin.');
    }

    /**
     * The resolved department reference.
     */
    public function departmentRef(): string
    {
        return $this->departmentRef;
    }

    /**
     * The Manager plugin name that leads the department.
     */
    public function managerRef(): string
    {
        return $this->managerRef;
    }

    /**
     * Structural equality across every attribute.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->departmentRef === $this->departmentRef
            && $other->managerRef === $this->managerRef;
    }
}
