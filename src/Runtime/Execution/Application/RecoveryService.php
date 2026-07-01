<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application;

use Nizam\Kernel\Domain\Clock;
use Nizam\Runtime\Execution\Application\Dto\ExecutionView;
use Nizam\Runtime\Execution\Application\Exception\ExecutionApplicationException;
use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\ExecutionState;
use Nizam\Runtime\Execution\Domain\Port\ExecutionEventPublisher;
use Nizam\Runtime\Execution\Domain\Port\ExecutionEventStore;
use Nizam\Runtime\Execution\Domain\Port\ExecutionRepository;

/**
 * The service that reconstructs a crashed execution from its event store and marks it Recovered.
 *
 * After a crash the current snapshot in the repository may lag the truth in the append-only event
 * store; recovery closes that gap. Given a failed execution's {@see ExecutionId}, it rebuilds the
 * aggregate purely from the {@see ExecutionEventStore} via {@see Execution::replay()}, then — only when
 * the rebuilt execution is in {@see ExecutionState::Failed} — records a {@see Execution::recover()}
 * transition into {@see ExecutionState::Recovered}, appends the new event to the store, saves the
 * refreshed snapshot through the {@see ExecutionRepository}, and publishes the recovery event. An
 * execution that is not failed cannot be recovered, which the service rejects. Recovery leaves the
 * execution resumable (Recovered may transition back to Running or Planning) without re-running any
 * work itself.
 */
final class RecoveryService
{
    /**
     * @param ExecutionEventStore     $eventStore The append-only stream the execution is rebuilt from.
     * @param ExecutionRepository     $repository The snapshot store the recovered execution is saved to.
     * @param ExecutionEventPublisher $publisher  The port that announces the recovery event.
     * @param Clock                   $clock      The time source the recovery transition is stamped with.
     */
    public function __construct(
        private readonly ExecutionEventStore $eventStore,
        private readonly ExecutionRepository $repository,
        private readonly ExecutionEventPublisher $publisher,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Rebuild the execution from its events, mark it Recovered, and persist and publish the change.
     *
     * @param ExecutionId $executionId The failed execution to recover.
     *
     * @throws ExecutionApplicationException When the store holds no events, or the execution is not failed.
     *
     * @return ExecutionView The recovered execution's read-only view (state {@see ExecutionState::Recovered}).
     */
    public function recover(ExecutionId $executionId): ExecutionView
    {
        $events = $this->eventStore->stream($executionId);
        if ($events === []) {
            throw ExecutionApplicationException::noEventsToReplay($executionId->toString());
        }

        $execution = Execution::replay($events);
        if ($execution->state() !== ExecutionState::Failed) {
            throw ExecutionApplicationException::notRecoverable(
                $executionId->toString(),
                $execution->state()->value,
            );
        }

        $execution->recover($this->clock);

        $recorded = $execution->pullDomainEvents();
        $this->eventStore->append($executionId, $recorded);
        $this->repository->save($execution);
        $this->publisher->publish($recorded);

        return ExecutionView::fromDomain($execution);
    }
}
