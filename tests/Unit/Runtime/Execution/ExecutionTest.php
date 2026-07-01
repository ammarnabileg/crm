<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Runtime\Execution;

use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Runtime\Execution\Domain\Event\ExecutionApproved;
use Nizam\Runtime\Execution\Domain\Event\ExecutionCancelled;
use Nizam\Runtime\Execution\Domain\Event\ExecutionCompleted;
use Nizam\Runtime\Execution\Domain\Event\ExecutionFailed;
use Nizam\Runtime\Execution\Domain\Event\ExecutionPlanned;
use Nizam\Runtime\Execution\Domain\Event\ExecutionRecovered;
use Nizam\Runtime\Execution\Domain\Event\ExecutionRetried;
use Nizam\Runtime\Execution\Domain\Event\ExecutionSentToReview;
use Nizam\Runtime\Execution\Domain\Event\ExecutionStarted;
use Nizam\Runtime\Execution\Domain\Event\ExecutionStepCompleted;
use Nizam\Runtime\Execution\Domain\Event\ExecutionStepStarted;
use Nizam\Runtime\Execution\Domain\Event\WorkTaskAssigned;
use Nizam\Runtime\Execution\Domain\Exception\IllegalExecutionTransition;
use Nizam\Runtime\Execution\Domain\Exception\RetryExhausted;
use Nizam\Runtime\Execution\Domain\Exception\UnknownStepException;
use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\ExecutionState;
use Nizam\Runtime\Execution\Domain\ExecutionStep;
use Nizam\Runtime\Execution\Domain\ExecutionStepId;
use Nizam\Runtime\Execution\Domain\ValueObject\BackoffStrategy;
use Nizam\Runtime\Execution\Domain\ValueObject\ExecutionMetadata;
use Nizam\Runtime\Execution\Domain\ValueObject\RetryPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\TimeoutPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Drives the event-sourced {@see Execution} aggregate through its lifecycle: the happy path and the
 * events it emits, failure and recovery, cancellation, retry-budget enforcement, and replay fidelity.
 */
#[CoversClass(Execution::class)]
final class ExecutionTest extends TestCase
{
    private MutableTestClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MutableTestClock();
    }

    private function metadata(): ExecutionMetadata
    {
        return new ExecutionMetadata(
            tenantId: TenantId::generate(),
            userId: null,
            departmentRef: 'sales',
            managerRef: 'runtime.fake-manager',
            intentRef: 'crm.lead.enrich',
            labels: ['channel' => 'api'],
        );
    }

    private function start(?RetryPolicy $retryPolicy = null): Execution
    {
        return Execution::start(
            ExecutionId::generate(),
            $this->metadata(),
            $retryPolicy ?? RetryPolicy::default(),
            TimeoutPolicy::default(),
            $this->clock,
        );
    }

    private function workerResult(bool $withError = false): WorkerResult
    {
        return new WorkerResult(
            taskResult: ['ok' => true],
            evidence: [],
            reasoningSummary: 'did the work',
            confidence: 0.9,
            executionTimeMs: 12,
            executionCostMicros: 34,
            resourcesUsed: ['tokens' => 100],
            automationSelected: 'automation.mailer',
            errors: $withError ? ['boom'] : [],
        );
    }

    /**
     * @param list<DomainEvent> $events
     *
     * @return list<class-string>
     */
    private function classesOf(array $events): array
    {
        return array_map(static fn (DomainEvent $e): string => $e::class, $events);
    }

    public function testStartEntersPendingAndRecordsExecutionStarted(): void
    {
        $execution = $this->start();

        self::assertSame(ExecutionState::Pending, $execution->state());
        self::assertSame(1, $execution->attempts());
        // The birth event is applied on start, advancing the version off its initial zero.
        self::assertSame(1, $execution->version());

        $events = $execution->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ExecutionStarted::class, $events[0]);
        self::assertCount(1, $execution->timeline()->entries());
    }

    public function testHappyPathEmitsTheCorrectOrderedEvents(): void
    {
        $execution = $this->start();
        $stepId = ExecutionStepId::generate();

        $execution->plan($this->clock);
        $execution->assign(
            [ExecutionStep::assign($stepId, 'enrich', 'runtime.fake-worker')],
            $this->clock,
        );
        $execution->run($this->clock);
        $execution->completeStep($stepId, $this->workerResult(), $this->clock);
        $execution->toReview($this->clock);
        $execution->approve('reviewer@acme', $this->clock);
        $execution->complete($this->clock);

        self::assertSame(ExecutionState::Completed, $execution->state());
        self::assertTrue($execution->state()->isTerminal());

        self::assertSame(
            [
                ExecutionStarted::class,
                ExecutionPlanned::class,
                WorkTaskAssigned::class,
                ExecutionStepStarted::class,
                ExecutionStepCompleted::class,
                ExecutionSentToReview::class,
                ExecutionApproved::class,
                ExecutionCompleted::class,
            ],
            $this->classesOf($execution->pullDomainEvents()),
        );
    }

    public function testCompletingAStepFoldsInCostAndPerformance(): void
    {
        $execution = $this->start();
        $stepId = ExecutionStepId::generate();
        $execution->plan($this->clock);
        $execution->assign([ExecutionStep::assign($stepId, 'enrich', 'runtime.fake-worker')], $this->clock);
        $execution->run($this->clock);
        $this->clock->advanceMs(12);
        $execution->completeStep($stepId, $this->workerResult(), $this->clock);

        self::assertSame(100, $execution->cost()->tokens());
        self::assertSame(34, $execution->cost()->currencyMicros());
        self::assertSame(1, $execution->performance()->stepCount());
    }

    public function testFailThenRecoverTransitionsThroughFailedToRecovered(): void
    {
        $execution = $this->start();
        $execution->plan($this->clock);
        $execution->fail('planning blew up', $this->clock);

        self::assertSame(ExecutionState::Failed, $execution->state());

        $execution->recover($this->clock);
        self::assertSame(ExecutionState::Recovered, $execution->state());

        self::assertSame(
            [
                ExecutionStarted::class,
                ExecutionPlanned::class,
                ExecutionFailed::class,
                ExecutionRecovered::class,
            ],
            $this->classesOf($execution->pullDomainEvents()),
        );
    }

    public function testCancelFromPendingReachesTerminalCancelled(): void
    {
        $execution = $this->start();

        $execution->cancel('operator@acme', $this->clock);

        self::assertSame(ExecutionState::Cancelled, $execution->state());
        self::assertTrue($execution->state()->isTerminal());

        // No move is legal out of a terminal state.
        $this->expectException(IllegalExecutionTransition::class);
        $execution->plan($this->clock);
    }

    public function testRetryHonoursThePolicyAndThrowsWhenExhausted(): void
    {
        // maxAttempts = 2 => one initial attempt plus exactly one retry.
        $policy = new RetryPolicy(2, 0, BackoffStrategy::Fixed, false);
        $execution = $this->start($policy);
        $stepId = ExecutionStepId::generate();
        $execution->plan($this->clock);
        $execution->assign([ExecutionStep::assign($stepId, 'enrich', 'runtime.fake-worker')], $this->clock);
        $execution->run($this->clock);

        // Attempt 1 has been made; the first retry is permitted and takes us to Retrying (attempt 2).
        $execution->retry('transient failure', $this->clock);
        self::assertSame(ExecutionState::Retrying, $execution->state());
        self::assertSame(2, $execution->attempts());
        self::assertSame(1, $execution->performance()->retryCount());

        // Get back to Running, then attempt a second retry: the budget (2) is now exhausted.
        $execution->beginStep($stepId, $this->clock);
        self::assertSame(ExecutionState::Running, $execution->state());

        $this->expectException(RetryExhausted::class);
        $execution->retry('again', $this->clock);
    }

    public function testRetryDoesNotTransitionWhenExhausted(): void
    {
        $policy = RetryPolicy::none(); // one attempt, no retries.
        $execution = $this->start($policy);
        $stepId = ExecutionStepId::generate();
        $execution->plan($this->clock);
        $execution->assign([ExecutionStep::assign($stepId, 'enrich', 'runtime.fake-worker')], $this->clock);
        $execution->run($this->clock);

        try {
            $execution->retry('nope', $this->clock);
            self::fail('Expected RetryExhausted.');
        } catch (RetryExhausted) {
            // The failed retry must leave the execution untouched (still Running, one attempt).
            self::assertSame(ExecutionState::Running, $execution->state());
            self::assertSame(1, $execution->attempts());
        }
    }

    public function testBeginningAnUnknownStepThrows(): void
    {
        $execution = $this->start();
        $execution->plan($this->clock);
        $execution->assign(
            [ExecutionStep::assign(ExecutionStepId::generate(), 'enrich', 'runtime.fake-worker')],
            $this->clock,
        );

        $this->expectException(UnknownStepException::class);
        $execution->beginStep(ExecutionStepId::generate(), $this->clock);
    }

    public function testWaitingIsDerivedWhenAStepCompletesWithWorkRemaining(): void
    {
        $execution = $this->start();
        $stepA = ExecutionStepId::generate();
        $stepB = ExecutionStepId::generate();
        $execution->plan($this->clock);
        $execution->assign(
            [
                ExecutionStep::assign($stepA, 'a', 'runtime.fake-worker'),
                ExecutionStep::assign($stepB, 'b', 'runtime.fake-worker'),
            ],
            $this->clock,
        );
        $execution->run($this->clock);

        // Completing the first of two steps leaves work pending, so the execution waits.
        $execution->completeStep($stepA, $this->workerResult(), $this->clock);
        self::assertSame(ExecutionState::Waiting, $execution->state());
    }

    public function testReplayReconstitutesIdenticalState(): void
    {
        $execution = $this->start();
        $stepId = ExecutionStepId::generate();
        $execution->plan($this->clock);
        $this->clock->advance(1);
        $execution->assign([ExecutionStep::assign($stepId, 'enrich', 'runtime.fake-worker')], $this->clock);
        $this->clock->advance(1);
        $execution->run($this->clock);
        $this->clock->advanceMs(50);
        $execution->completeStep($stepId, $this->workerResult(), $this->clock);
        $execution->toReview($this->clock);
        $execution->approve('reviewer@acme', $this->clock);
        $execution->complete($this->clock);

        $events = $execution->pullDomainEvents();
        $replayed = Execution::replay($events);

        self::assertTrue($replayed->executionId()->equals($execution->executionId()));
        self::assertSame($execution->state(), $replayed->state());
        self::assertSame($execution->attempts(), $replayed->attempts());
        self::assertSame($execution->cost()->tokens(), $replayed->cost()->tokens());
        self::assertSame($execution->cost()->currencyMicros(), $replayed->cost()->currencyMicros());
        self::assertSame($execution->performance()->stepCount(), $replayed->performance()->stepCount());
        self::assertSame(
            count($execution->timeline()->entries()),
            count($replayed->timeline()->entries()),
        );
        self::assertCount(count($execution->steps()), $replayed->steps());

        // A replayed aggregate is read-only reconstruction: it carries no pending events.
        self::assertFalse($replayed->hasRecordedEvents());
    }

    public function testReplayRequiresAStartedEventFirst(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Execution::replay([new ExecutionRetried(ExecutionId::generate(), 2, 'x', $this->clock->now())]);
    }

    public function testReplayOfARetryReconstructsAttemptCount(): void
    {
        $policy = new RetryPolicy(3, 0, BackoffStrategy::Fixed, false);
        $execution = $this->start($policy);
        $stepId = ExecutionStepId::generate();
        $execution->plan($this->clock);
        $execution->assign([ExecutionStep::assign($stepId, 'enrich', 'runtime.fake-worker')], $this->clock);
        $execution->run($this->clock);
        $execution->retry('transient', $this->clock);

        $replayed = Execution::replay($execution->pullDomainEvents());

        self::assertSame(ExecutionState::Retrying, $replayed->state());
        self::assertSame(2, $replayed->attempts());
        self::assertSame(1, $replayed->performance()->retryCount());
    }

    public function testCancelEventCarriesTheActor(): void
    {
        $execution = $this->start();
        $execution->cancel('operator@acme', $this->clock);

        $events = $execution->pullDomainEvents();
        $cancelled = $events[1];
        self::assertInstanceOf(ExecutionCancelled::class, $cancelled);
        self::assertSame('operator@acme', $cancelled->cancelledBy());
    }
}
