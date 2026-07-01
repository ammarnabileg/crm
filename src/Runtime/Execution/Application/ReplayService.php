<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application;

use Nizam\Runtime\Execution\Application\Dto\ExecutionReplayView;
use Nizam\Runtime\Execution\Application\Exception\ExecutionApplicationException;
use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\Port\ExecutionEventStore;

/**
 * The read-only service that rebuilds an execution's state from its event stream for audit and debug.
 *
 * Replay is the event-sourcing pay-off made observable: given an {@see ExecutionId}, it reads the
 * append-only stream from the {@see ExecutionEventStore} and reconstitutes the aggregate purely from
 * those events via {@see Execution::replay()}, then projects it into an {@see ExecutionReplayView}
 * naming every event applied and the state they arrived at. It is strictly side-effect-free — nothing
 * is persisted, published, or mutated — so operators can inspect exactly how an execution reached its
 * state without changing anything. An empty stream is an error, since a real execution always begins
 * with an {@see \Nizam\Runtime\Execution\Domain\Event\ExecutionStarted}.
 */
final class ReplayService
{
    /**
     * @param ExecutionEventStore $eventStore The append-only stream the replay is rebuilt from.
     */
    public function __construct(
        private readonly ExecutionEventStore $eventStore,
    ) {
    }

    /**
     * Rebuild an execution from its event stream and project it into a read-only replay view.
     *
     * @param ExecutionId $executionId The execution to replay.
     *
     * @throws ExecutionApplicationException When the store holds no events for the execution.
     *
     * @return ExecutionReplayView The reconstituted state plus the ordered names of the replayed events.
     */
    public function replay(ExecutionId $executionId): ExecutionReplayView
    {
        $events = $this->eventStore->stream($executionId);
        if ($events === []) {
            throw ExecutionApplicationException::noEventsToReplay($executionId->toString());
        }

        $execution = Execution::replay($events);

        $eventNames = [];
        foreach ($events as $event) {
            $eventNames[] = $event->eventName();
        }

        return ExecutionReplayView::fromReplay($execution, $eventNames);
    }
}
