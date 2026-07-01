<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\ValueObject;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;

/**
 * A single, self-contained unit of work the {@see \Nizam\Runtime\Orchestration\WorkerCoordinator}
 * dispatches to a worker plugin.
 *
 * A work task names the capability it requires ({@see self::capability()} — how the coordinator selects
 * the worker), the worker reference it is bound to, and an opaque payload the worker interprets. It is
 * produced by the Manager during planning and never by a worker; when a worker needs more help it does
 * not spawn tasks itself — it returns a {@see WorkerResult} and the manager (via the coordinator's
 * collaborative re-entry) issues fresh tasks. Being a value object it is immutable and self-validating.
 */
final class WorkTask implements ValueObject
{
    /** @var array<string, mixed> */
    private readonly array $payload;

    /**
     * @param string               $taskId     A stable identifier for the task within its batch.
     * @param string               $capability The capability the task requires (used for worker routing).
     * @param string               $workerRef  The worker plugin the task is dispatched to.
     * @param array<string, mixed> $payload    The opaque payload the worker interprets.
     * @param string               $intent     A human-readable description of what the task should achieve.
     */
    public function __construct(
        private readonly string $taskId,
        private readonly string $capability,
        private readonly string $workerRef,
        array $payload,
        private readonly string $intent,
    ) {
        Assert::notEmpty($taskId, 'A work task must have a non-empty task id.');
        Assert::notEmpty($capability, 'A work task must name the capability it requires.');
        Assert::notEmpty($workerRef, 'A work task must reference the worker it is dispatched to.');
        Assert::notEmpty($intent, 'A work task must describe its intent.');

        $normalized = [];
        foreach ($payload as $key => $value) {
            Assert::that(is_string($key) && $key !== '', 'Work task payload keys must be non-empty strings.');
            $normalized[$key] = $value;
        }
        $this->payload = $normalized;
    }

    /**
     * The stable identifier for the task within its batch.
     */
    public function taskId(): string
    {
        return $this->taskId;
    }

    /**
     * The capability the task requires (used for worker routing).
     */
    public function capability(): string
    {
        return $this->capability;
    }

    /**
     * The worker plugin the task is dispatched to.
     */
    public function workerRef(): string
    {
        return $this->workerRef;
    }

    /**
     * The opaque payload the worker interprets.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * The human-readable description of what the task should achieve.
     */
    public function intent(): string
    {
        return $this->intent;
    }

    /**
     * Structural equality across every attribute.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->taskId === $this->taskId
            && $other->capability === $this->capability
            && $other->workerRef === $this->workerRef
            && $other->payload === $this->payload
            && $other->intent === $this->intent;
    }

    /**
     * A scalar-only representation suitable for logging and transport.
     *
     * @return array{taskId: string, capability: string, workerRef: string, payload: array<string, mixed>, intent: string}
     */
    public function toArray(): array
    {
        return [
            'taskId' => $this->taskId,
            'capability' => $this->capability,
            'workerRef' => $this->workerRef,
            'payload' => $this->payload,
            'intent' => $this->intent,
        ];
    }
}
