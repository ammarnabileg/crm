<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Runtime\Execution;

use Nizam\Runtime\Execution\Domain\Exception\IllegalExecutionTransition;
use Nizam\Runtime\Execution\Domain\ExecutionState;
use Nizam\Runtime\Execution\Domain\ExecutionStateMachine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the ADR-0018 transition graph: every legal move is permitted, representative illegal moves
 * throw, terminal states have no exits, and the graph covers all thirteen states exactly.
 */
#[CoversClass(ExecutionStateMachine::class)]
final class ExecutionStateMachineTest extends TestCase
{
    /**
     * Every state named in ADR-0018 must appear as a source in the graph, and no others.
     */
    public function testGraphCoversEveryStateExactlyOnce(): void
    {
        $keys = array_keys(ExecutionStateMachine::allowedTransitions());
        sort($keys);

        $expected = array_map(static fn (ExecutionState $s): string => $s->value, ExecutionState::cases());
        sort($expected);

        self::assertSame($expected, $keys);
    }

    /**
     * @return iterable<string, array{ExecutionState, ExecutionState}>
     */
    public static function legalTransitions(): iterable
    {
        yield 'Pending->Planning' => [ExecutionState::Pending, ExecutionState::Planning];
        yield 'Pending->Cancelled' => [ExecutionState::Pending, ExecutionState::Cancelled];
        yield 'Planning->Assigned' => [ExecutionState::Planning, ExecutionState::Assigned];
        yield 'Planning->Failed' => [ExecutionState::Planning, ExecutionState::Failed];
        yield 'Planning->Cancelled' => [ExecutionState::Planning, ExecutionState::Cancelled];
        yield 'Assigned->Running' => [ExecutionState::Assigned, ExecutionState::Running];
        yield 'Assigned->Waiting' => [ExecutionState::Assigned, ExecutionState::Waiting];
        yield 'Assigned->Cancelled' => [ExecutionState::Assigned, ExecutionState::Cancelled];
        yield 'Waiting->Running' => [ExecutionState::Waiting, ExecutionState::Running];
        yield 'Waiting->Cancelled' => [ExecutionState::Waiting, ExecutionState::Cancelled];
        yield 'Waiting->Failed' => [ExecutionState::Waiting, ExecutionState::Failed];
        yield 'Running->Review' => [ExecutionState::Running, ExecutionState::Review];
        yield 'Running->Retrying' => [ExecutionState::Running, ExecutionState::Retrying];
        yield 'Running->Completed' => [ExecutionState::Running, ExecutionState::Completed];
        yield 'Running->Failed' => [ExecutionState::Running, ExecutionState::Failed];
        yield 'Running->Waiting' => [ExecutionState::Running, ExecutionState::Waiting];
        yield 'Running->Cancelled' => [ExecutionState::Running, ExecutionState::Cancelled];
        yield 'Retrying->Running' => [ExecutionState::Retrying, ExecutionState::Running];
        yield 'Retrying->Failed' => [ExecutionState::Retrying, ExecutionState::Failed];
        yield 'Retrying->Cancelled' => [ExecutionState::Retrying, ExecutionState::Cancelled];
        yield 'Review->Approved' => [ExecutionState::Review, ExecutionState::Approved];
        yield 'Review->Rejected' => [ExecutionState::Review, ExecutionState::Rejected];
        yield 'Review->Retrying' => [ExecutionState::Review, ExecutionState::Retrying];
        yield 'Approved->Completed' => [ExecutionState::Approved, ExecutionState::Completed];
        yield 'Rejected->Retrying' => [ExecutionState::Rejected, ExecutionState::Retrying];
        yield 'Rejected->Failed' => [ExecutionState::Rejected, ExecutionState::Failed];
        yield 'Failed->Recovered' => [ExecutionState::Failed, ExecutionState::Recovered];
        yield 'Failed->Cancelled' => [ExecutionState::Failed, ExecutionState::Cancelled];
        yield 'Recovered->Running' => [ExecutionState::Recovered, ExecutionState::Running];
        yield 'Recovered->Planning' => [ExecutionState::Recovered, ExecutionState::Planning];
    }

    #[DataProvider('legalTransitions')]
    public function testEveryLegalTransitionIsPermitted(ExecutionState $from, ExecutionState $to): void
    {
        self::assertTrue(ExecutionStateMachine::canTransition($from, $to));

        // The throwing guard must not throw for a legal move.
        ExecutionStateMachine::assert($from, $to);
        $this->addToAssertionCount(1);
    }

    /**
     * The provider above must enumerate exactly the edges present in the graph — no more, no fewer.
     */
    public function testProviderEnumeratesEveryEdgeInTheGraph(): void
    {
        $graphEdges = 0;
        foreach (ExecutionStateMachine::allowedTransitions() as $targets) {
            $graphEdges += count($targets);
        }

        self::assertSame($graphEdges, iterator_count(self::legalTransitions()));
    }

    /**
     * @return iterable<string, array{ExecutionState, ExecutionState}>
     */
    public static function illegalTransitions(): iterable
    {
        yield 'Pending->Running (must plan first)' => [ExecutionState::Pending, ExecutionState::Running];
        yield 'Pending->Completed' => [ExecutionState::Pending, ExecutionState::Completed];
        yield 'Planning->Running (must assign first)' => [ExecutionState::Planning, ExecutionState::Running];
        yield 'Assigned->Completed' => [ExecutionState::Assigned, ExecutionState::Completed];
        yield 'Running->Approved (must go through review)' => [ExecutionState::Running, ExecutionState::Approved];
        yield 'Approved->Rejected' => [ExecutionState::Approved, ExecutionState::Rejected];
        yield 'Failed->Running (must recover first)' => [ExecutionState::Failed, ExecutionState::Running];
        yield 'Review->Completed (must approve first)' => [ExecutionState::Review, ExecutionState::Completed];
        yield 'Recovered->Completed' => [ExecutionState::Recovered, ExecutionState::Completed];
    }

    #[DataProvider('illegalTransitions')]
    public function testIllegalTransitionsAreRejected(ExecutionState $from, ExecutionState $to): void
    {
        self::assertFalse(ExecutionStateMachine::canTransition($from, $to));

        $this->expectException(IllegalExecutionTransition::class);
        ExecutionStateMachine::assert($from, $to);
    }

    public function testTerminalStatesHaveNoExits(): void
    {
        self::assertTrue(ExecutionState::Completed->isTerminal());
        self::assertTrue(ExecutionState::Cancelled->isTerminal());

        self::assertSame([], ExecutionStateMachine::allowedTransitions()[ExecutionState::Completed->value]);
        self::assertSame([], ExecutionStateMachine::allowedTransitions()[ExecutionState::Cancelled->value]);

        foreach (ExecutionState::cases() as $target) {
            self::assertFalse(ExecutionStateMachine::canTransition(ExecutionState::Completed, $target));
            self::assertFalse(ExecutionStateMachine::canTransition(ExecutionState::Cancelled, $target));
        }
    }

    public function testNonTerminalStatesAreNotFlaggedTerminal(): void
    {
        foreach (ExecutionState::cases() as $state) {
            if ($state === ExecutionState::Completed || $state === ExecutionState::Cancelled) {
                continue;
            }

            self::assertFalse($state->isTerminal(), sprintf('%s must not be terminal.', $state->value));
        }
    }

    public function testTheErrorNamesBothOffendingStates(): void
    {
        try {
            ExecutionStateMachine::assert(ExecutionState::Completed, ExecutionState::Running);
            self::fail('Expected IllegalExecutionTransition.');
        } catch (IllegalExecutionTransition $e) {
            self::assertSame(IllegalExecutionTransition::CODE, $e->errorCode());
            self::assertStringContainsString('completed', $e->getMessage());
            self::assertStringContainsString('running', $e->getMessage());
        }
    }
}
