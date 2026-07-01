<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\ValueObject;

use DateTimeImmutable;
use Nizam\Kernel\Domain\ValueObject;
use Nizam\Runtime\Execution\Domain\ExecutionState;

/**
 * One immutable, human-readable step on an execution's path: a state, an instant, and a note.
 *
 * The {@see ExecutionTimeline} is the ordered sequence of these entries — the story of what happened
 * to an execution, in business terms, without stack traces. Each entry records the
 * {@see ExecutionState} the execution entered, when it entered it, and a short human note explaining
 * why. Being a value object, entries are compared by value.
 */
final class TimelineEntry implements ValueObject
{
    /**
     * @param ExecutionState    $state The state the execution entered.
     * @param DateTimeImmutable $at    When the state was entered.
     * @param string            $note  A short human-readable explanation of the change.
     */
    public function __construct(
        private readonly ExecutionState $state,
        private readonly DateTimeImmutable $at,
        private readonly string $note,
    ) {
    }

    /**
     * The state the execution entered.
     */
    public function state(): ExecutionState
    {
        return $this->state;
    }

    /**
     * When the state was entered.
     */
    public function at(): DateTimeImmutable
    {
        return $this->at;
    }

    /**
     * The short human-readable explanation of the change.
     */
    public function note(): string
    {
        return $this->note;
    }

    /**
     * Structural equality across every attribute.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->state === $this->state
            && $other->at->getTimestamp() === $this->at->getTimestamp()
            && $other->note === $this->note;
    }

    /**
     * A scalar-only representation suitable for JSON persistence and read models.
     *
     * @return array{state: string, at: string, note: string}
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'at' => $this->at->format(DateTimeImmutable::ATOM),
            'note' => $this->note,
        ];
    }
}
