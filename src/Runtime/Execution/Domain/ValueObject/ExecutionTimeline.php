<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\ValueObject;

use DateTimeImmutable;
use Nizam\Kernel\Domain\ValueObject;
use Nizam\Runtime\Execution\Domain\ExecutionState;

/**
 * The ordered, append-only, human-readable path an execution has taken.
 *
 * A timeline is a list of {@see TimelineEntry}s in the order the execution entered each state. It is
 * immutable: {@see self::append()} returns a new timeline with the extra entry rather than mutating
 * in place, which keeps the aggregate's history a value and makes replay reconstruction trivial.
 * This is the friendly, stack-trace-free record surfaced to the non-technical Execution Monitor.
 */
final class ExecutionTimeline implements ValueObject
{
    /** @var list<TimelineEntry> */
    private readonly array $entries;

    /**
     * @param list<TimelineEntry> $entries The ordered timeline entries.
     */
    public function __construct(array $entries = [])
    {
        $values = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof TimelineEntry) {
                throw new \InvalidArgumentException('A timeline may only contain TimelineEntry instances.');
            }
            $values[] = $entry;
        }
        $this->entries = $values;
    }

    /**
     * An empty timeline.
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Append a new entry, returning a new timeline (the original is unchanged).
     *
     * @param ExecutionState    $state The state entered.
     * @param DateTimeImmutable $at    When it was entered.
     * @param string            $note  A short human-readable explanation.
     */
    public function append(ExecutionState $state, DateTimeImmutable $at, string $note): self
    {
        return new self([...$this->entries, new TimelineEntry($state, $at, $note)]);
    }

    /**
     * The ordered timeline entries.
     *
     * @return list<TimelineEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * The most recent entry, or null when the timeline is empty.
     */
    public function latest(): ?TimelineEntry
    {
        $count = count($this->entries);

        return $count === 0 ? null : $this->entries[$count - 1];
    }

    /**
     * The number of entries.
     */
    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * Structural equality: same entries, in the same order.
     */
    public function equals(ValueObject $other): bool
    {
        if (!$other instanceof self || count($other->entries) !== count($this->entries)) {
            return false;
        }

        foreach ($this->entries as $index => $entry) {
            if (!$entry->equals($other->entries[$index])) {
                return false;
            }
        }

        return true;
    }

    /**
     * A scalar-only representation suitable for JSON persistence and read models.
     *
     * @return list<array{state: string, at: string, note: string}>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (TimelineEntry $entry): array => $entry->toArray(),
            $this->entries,
        );
    }
}
