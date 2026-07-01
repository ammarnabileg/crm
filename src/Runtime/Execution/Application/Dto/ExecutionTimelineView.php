<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application\Dto;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\ValueObject\ExecutionTimeline;

/**
 * A flat, read-only projection of an {@see ExecutionTimeline} for the Execution Monitor and audit.
 *
 * The view carries the execution's identity and the ordered, human-readable timeline entries — each a
 * state, an instant, and a friendly note — as scalar maps. It is the stack-trace-free story surfaced
 * to non-technical operators: what happened to an execution, in business terms, in order. Being a
 * read model it is compared by value so results built from it can be compared and asserted in tests.
 */
final class ExecutionTimelineView implements ValueObject
{
    /** @var list<array{state: string, at: string, note: string}> */
    private readonly array $entries;

    /**
     * @param string                                              $executionId The execution the timeline belongs to.
     * @param list<array{state: string, at: string, note: string}> $entries    The ordered timeline entries as scalar maps.
     */
    public function __construct(
        private readonly string $executionId,
        array $entries,
    ) {
        $this->entries = array_values($entries);
    }

    /**
     * Project a domain timeline into its read-only view.
     */
    public static function fromDomain(ExecutionId $executionId, ExecutionTimeline $timeline): self
    {
        return new self($executionId->toString(), $timeline->toArray());
    }

    /**
     * The execution the timeline belongs to.
     */
    public function executionId(): string
    {
        return $this->executionId;
    }

    /**
     * The ordered timeline entries as scalar maps.
     *
     * @return list<array{state: string, at: string, note: string}>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * The number of timeline entries.
     */
    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * The most recent timeline entry as a scalar map, or null when empty.
     *
     * @return array{state: string, at: string, note: string}|null
     */
    public function latest(): ?array
    {
        $count = count($this->entries);

        return $count === 0 ? null : $this->entries[$count - 1];
    }

    /**
     * Structural equality: same execution and same entries in the same order.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->executionId === $this->executionId
            && $other->entries === $this->entries;
    }

    /**
     * A scalar-only representation suitable for JSON serialization and transport.
     *
     * @return array{executionId: string, entries: list<array{state: string, at: string, note: string}>}
     */
    public function toArray(): array
    {
        return [
            'executionId' => $this->executionId,
            'entries' => $this->entries,
        ];
    }
}
