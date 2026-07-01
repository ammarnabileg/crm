<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application\Dto;

use DateTimeInterface;
use Nizam\Runtime\Execution\Domain\ExecutionStep;

/**
 * A flat, read-only projection of an {@see ExecutionStep} for callers outside the domain.
 *
 * The view exposes a step's identity, name, lifecycle state, worker reference, attempt counter, start
 * and finish instants, wall-clock duration, and — when the step has completed — its worker result as
 * a scalar map. It is transport-safe: it never leaks a domain entity or a mutable {@see ExecutionStep}.
 */
final class ExecutionStepView
{
    /**
     * @param string                    $stepId     The step's identity.
     * @param string                    $name       The human-readable step name.
     * @param string                    $state      The step's lifecycle state value.
     * @param string                    $workerRef  The worker plugin the step is assigned to.
     * @param int                       $attempt    Which attempt this is (1-based).
     * @param string|null               $startedAt  When the step began (ISO-8601), or null.
     * @param string|null               $finishedAt When the step finished (ISO-8601), or null.
     * @param int|null                  $durationMs The step's wall-clock duration in ms, or null.
     * @param array<string, mixed>|null $result     The worker result as a scalar map, or null.
     */
    public function __construct(
        public readonly string $stepId,
        public readonly string $name,
        public readonly string $state,
        public readonly string $workerRef,
        public readonly int $attempt,
        public readonly ?string $startedAt,
        public readonly ?string $finishedAt,
        public readonly ?int $durationMs,
        public readonly ?array $result,
    ) {
    }

    /**
     * Project a domain step into its read-only view.
     */
    public static function fromDomain(ExecutionStep $step): self
    {
        $result = $step->result();

        return new self(
            stepId: $step->stepId()->toString(),
            name: $step->name(),
            state: $step->state()->value,
            workerRef: $step->workerRef(),
            attempt: $step->attempt(),
            startedAt: $step->startedAt()?->format(DateTimeInterface::ATOM),
            finishedAt: $step->finishedAt()?->format(DateTimeInterface::ATOM),
            durationMs: $step->durationMs(),
            result: $result?->toArray(),
        );
    }

    /**
     * A scalar-only representation suitable for JSON serialization and transport.
     *
     * @return array{
     *     stepId: string,
     *     name: string,
     *     state: string,
     *     workerRef: string,
     *     attempt: int,
     *     startedAt: string|null,
     *     finishedAt: string|null,
     *     durationMs: int|null,
     *     result: array<string, mixed>|null
     * }
     */
    public function toArray(): array
    {
        return [
            'stepId' => $this->stepId,
            'name' => $this->name,
            'state' => $this->state,
            'workerRef' => $this->workerRef,
            'attempt' => $this->attempt,
            'startedAt' => $this->startedAt,
            'finishedAt' => $this->finishedAt,
            'durationMs' => $this->durationMs,
            'result' => $this->result,
        ];
    }
}
