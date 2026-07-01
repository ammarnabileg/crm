<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Runtime\Execution;

use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Runtime\Execution\Application\Exception\ExecutionApplicationException;
use Nizam\Runtime\Execution\Application\RecoveryService;
use Nizam\Runtime\Execution\Domain\Event\ExecutionRecovered;
use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\ExecutionState;
use Nizam\Runtime\Execution\Domain\Port\ExecutionEventPublisher;
use Nizam\Runtime\Execution\Domain\ValueObject\ExecutionMetadata;
use Nizam\Runtime\Execution\Domain\ValueObject\RetryPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\TimeoutPolicy;
use Nizam\Runtime\Infrastructure\Persistence\InMemory\InMemoryExecutionEventStore;
use Nizam\Runtime\Infrastructure\Persistence\InMemory\InMemoryExecutionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A collecting {@see ExecutionEventPublisher} that records what recovery published.
 */
final class CollectingRecoveryPublisher implements ExecutionEventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        $this->published = [...$this->published, ...array_values($events)];
    }
}

/**
 * Verifies the {@see RecoveryService} rebuilds a crashed execution from its event stream, transitions a
 * failed execution to Recovered, and persists and publishes the recovery, while refusing non-failed and
 * empty streams.
 */
#[CoversClass(RecoveryService::class)]
final class RecoveryServiceTest extends TestCase
{
    private MutableTestClock $clock;
    private InMemoryExecutionEventStore $eventStore;
    private InMemoryExecutionRepository $repository;
    private CollectingRecoveryPublisher $publisher;

    protected function setUp(): void
    {
        $this->clock = new MutableTestClock();
        $this->eventStore = new InMemoryExecutionEventStore();
        $this->repository = new InMemoryExecutionRepository();
        $this->publisher = new CollectingRecoveryPublisher();
    }

    private function service(): RecoveryService
    {
        return new RecoveryService($this->eventStore, $this->repository, $this->publisher, $this->clock);
    }

    private function metadata(): ExecutionMetadata
    {
        return new ExecutionMetadata(
            tenantId: TenantId::generate(),
            userId: null,
            departmentRef: null,
            managerRef: null,
            intentRef: 'crm.lead.enrich',
        );
    }

    /**
     * Persist a failed execution's events into the store and return its id.
     */
    private function seedFailedExecution(): ExecutionId
    {
        $execution = Execution::start(
            ExecutionId::generate(),
            $this->metadata(),
            RetryPolicy::default(),
            TimeoutPolicy::default(),
            $this->clock,
        );
        $execution->plan($this->clock);
        $execution->fail('crashed mid-plan', $this->clock);

        $id = $execution->executionId();
        $this->eventStore->append($id, $execution->pullDomainEvents());

        return $id;
    }

    public function testRecoversAFailedExecutionAndMarksItRecovered(): void
    {
        $id = $this->seedFailedExecution();

        $view = $this->service()->recover($id);

        self::assertSame(ExecutionState::Recovered->value, $view->state);

        // The rebuilt-and-recovered snapshot is saved to the repository.
        $saved = $this->repository->ofId($id);
        self::assertNotNull($saved);
        self::assertSame(ExecutionState::Recovered, $saved->state());
    }

    public function testRecoveryAppendsTheRecoveredEventToTheStore(): void
    {
        $id = $this->seedFailedExecution();
        $before = count($this->eventStore->stream($id));

        $this->service()->recover($id);

        $stream = $this->eventStore->stream($id);
        self::assertCount($before + 1, $stream);
        self::assertInstanceOf(ExecutionRecovered::class, $stream[array_key_last($stream)]);
    }

    public function testRecoveryPublishesTheRecoveredEvent(): void
    {
        $id = $this->seedFailedExecution();

        $this->service()->recover($id);

        self::assertCount(1, $this->publisher->published);
        self::assertInstanceOf(ExecutionRecovered::class, $this->publisher->published[0]);
    }

    public function testRecoveringAnExecutionWithNoEventsThrows(): void
    {
        $this->expectException(ExecutionApplicationException::class);
        $this->expectExceptionMessageMatches('/NO_EVENTS_TO_REPLAY|no recorded events/');

        $this->service()->recover(ExecutionId::generate());
    }

    public function testRecoveringANonFailedExecutionThrows(): void
    {
        // A merely-pending execution is not recoverable.
        $execution = Execution::start(
            ExecutionId::generate(),
            $this->metadata(),
            RetryPolicy::default(),
            TimeoutPolicy::default(),
            $this->clock,
        );
        $id = $execution->executionId();
        $this->eventStore->append($id, $execution->pullDomainEvents());

        $this->expectException(ExecutionApplicationException::class);
        $this->expectExceptionMessageMatches('/NOT_RECOVERABLE|cannot be recovered/');

        $this->service()->recover($id);
    }
}
