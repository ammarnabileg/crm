<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Runtime\Execution;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Runtime\Execution\Application\Exception\ExecutionApplicationException;
use Nizam\Runtime\Execution\Application\ReplayService;
use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\ExecutionState;
use Nizam\Runtime\Execution\Domain\ExecutionStep;
use Nizam\Runtime\Execution\Domain\ExecutionStepId;
use Nizam\Runtime\Execution\Domain\ValueObject\ExecutionMetadata;
use Nizam\Runtime\Execution\Domain\ValueObject\RetryPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\TimeoutPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;
use Nizam\Runtime\Infrastructure\Persistence\InMemory\InMemoryExecutionEventStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the read-only {@see ReplayService}: it rebuilds an execution's final state and event names
 * from the stream without side effects, and rejects empty streams.
 */
#[CoversClass(ReplayService::class)]
final class ReplayServiceTest extends TestCase
{
    private MutableTestClock $clock;
    private InMemoryExecutionEventStore $eventStore;

    protected function setUp(): void
    {
        $this->clock = new MutableTestClock();
        $this->eventStore = new InMemoryExecutionEventStore();
    }

    /**
     * Seed a fully-completed execution's events and return its id.
     */
    private function seedCompletedExecution(): ExecutionId
    {
        $execution = Execution::start(
            ExecutionId::generate(),
            new ExecutionMetadata(
                tenantId: TenantId::generate(),
                userId: null,
                departmentRef: null,
                managerRef: null,
                intentRef: 'crm.lead.enrich',
            ),
            RetryPolicy::default(),
            TimeoutPolicy::default(),
            $this->clock,
        );
        $stepId = ExecutionStepId::generate();
        $execution->plan($this->clock);
        $execution->assign([ExecutionStep::assign($stepId, 'enrich', 'runtime.fake-worker')], $this->clock);
        $execution->run($this->clock);
        $execution->completeStep(
            $stepId,
            new WorkerResult(['ok' => true], [], 'done', 0.9, 5, 6, ['tokens' => 10]),
            $this->clock,
        );
        $execution->toReview($this->clock);
        $execution->approve('reviewer@acme', $this->clock);
        $execution->complete($this->clock);

        $id = $execution->executionId();
        $this->eventStore->append($id, $execution->pullDomainEvents());

        return $id;
    }

    public function testReplayRebuildsFinalStateAndEventNames(): void
    {
        $id = $this->seedCompletedExecution();
        $streamed = $this->eventStore->stream($id);

        $view = (new ReplayService($this->eventStore))->replay($id);

        self::assertSame(ExecutionState::Completed->value, $view->finalState());
        self::assertSame(count($streamed), $view->eventCount);
        self::assertSame('runtime.execution_started', $view->eventNames[0]);
        self::assertContains('runtime.execution_completed', $view->eventNames);
        self::assertSame($id->toString(), $view->execution->executionId);
    }

    public function testReplayIsSideEffectFree(): void
    {
        $id = $this->seedCompletedExecution();
        $before = $this->eventStore->stream($id);

        (new ReplayService($this->eventStore))->replay($id);

        // The stream is untouched: replay neither appends, mutates, nor deletes.
        self::assertSame($before, $this->eventStore->stream($id));
    }

    public function testReplayingAnUnknownExecutionThrows(): void
    {
        $this->expectException(ExecutionApplicationException::class);
        $this->expectExceptionMessageMatches('/NO_EVENTS_TO_REPLAY|no recorded events/');

        (new ReplayService($this->eventStore))->replay(ExecutionId::generate());
    }
}
