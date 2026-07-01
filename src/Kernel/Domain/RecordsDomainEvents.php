<?php

declare(strict_types=1);

namespace Nizam\Kernel\Domain;

/**
 * Gives an aggregate the ability to record domain events and hand them off for dispatch.
 *
 * State changes call {@see self::recordThat()} to buffer an event; the application layer calls
 * {@see self::pullDomainEvents()} once the change is persisted to retrieve and clear the buffer,
 * then dispatches the events. Buffering (rather than dispatching inline) keeps aggregates free of
 * infrastructure and ensures events fire only after the unit of work commits.
 */
trait RecordsDomainEvents
{
    /**
     * The events buffered since the last pull.
     *
     * @var array<int, DomainEvent>
     */
    private array $recordedEvents = [];

    /**
     * Buffer a domain event.
     */
    protected function recordThat(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * Return the buffered events and clear the buffer.
     *
     * @return array<int, DomainEvent>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    /**
     * Peek at the buffered events without clearing them.
     *
     * @return array<int, DomainEvent>
     */
    public function releaseEvents(): array
    {
        return $this->recordedEvents;
    }

    /**
     * Whether any events are currently buffered.
     */
    public function hasRecordedEvents(): bool
    {
        return $this->recordedEvents !== [];
    }
}
