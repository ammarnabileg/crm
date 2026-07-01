<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application\Dto;

use Nizam\Runtime\Execution\Domain\Execution;

/**
 * A read-only rebuild of an execution from its event stream, for audit and debugging.
 *
 * The {@see \Nizam\Runtime\Execution\Application\ReplayService} reconstitutes an execution purely from
 * its append-only event stream and projects it into this view: the full {@see ExecutionView} state as
 * of the last recorded event, the ordered names of the events replayed, and how many were applied. It
 * is strictly read-only — replaying never persists, publishes, or mutates anything — so operators can
 * inspect exactly how an execution reached its current state without side effects.
 */
final class ExecutionReplayView
{
    /**
     * @param ExecutionView $execution  The rebuilt execution state as a view.
     * @param list<string>  $eventNames The ordered names of the events replayed.
     * @param int           $eventCount How many events were applied.
     */
    public function __construct(
        public readonly ExecutionView $execution,
        public readonly array $eventNames,
        public readonly int $eventCount,
    ) {
    }

    /**
     * Build a replay view from a reconstituted execution and the stream of event names it was built from.
     *
     * @param Execution    $execution  The execution rebuilt from the event stream.
     * @param list<string> $eventNames The ordered names of the events replayed.
     */
    public static function fromReplay(Execution $execution, array $eventNames): self
    {
        return new self(
            execution: ExecutionView::fromDomain($execution),
            eventNames: array_values($eventNames),
            eventCount: count($eventNames),
        );
    }

    /**
     * The final lifecycle state the replay arrived at.
     */
    public function finalState(): string
    {
        return $this->execution->state;
    }

    /**
     * A scalar-only representation suitable for JSON serialization and transport.
     *
     * @return array{
     *     execution: array<string, mixed>,
     *     eventNames: list<string>,
     *     eventCount: int
     * }
     */
    public function toArray(): array
    {
        return [
            'execution' => $this->execution->toArray(),
            'eventNames' => $this->eventNames,
            'eventCount' => $this->eventCount,
        ];
    }
}
