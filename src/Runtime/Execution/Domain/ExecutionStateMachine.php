<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain;

use Nizam\Runtime\Execution\Domain\Exception\IllegalExecutionTransition;

/**
 * The authoritative, production-sane transition graph for an {@see Execution}, per ADR-0018.
 *
 * A stateless domain service: it owns the single source of truth for which {@see ExecutionState}
 * moves are legal and offers a non-throwing predicate ({@see self::canTransition()}) and a throwing
 * guard ({@see self::assert()}) that the {@see Execution} aggregate calls before every mutation. The
 * two terminal states — {@see ExecutionState::Completed} and {@see ExecutionState::Cancelled} — have
 * no outgoing edges. The class is not instantiable; all behavior is static and pure.
 */
final class ExecutionStateMachine
{
    /**
     * Not instantiable: the state machine is a pure, static domain service.
     */
    private function __construct()
    {
    }

    /**
     * The complete legal transition graph, mapping each state to the states it may move to.
     *
     * Terminal states ({@see ExecutionState::Completed}, {@see ExecutionState::Cancelled}) map to an
     * empty list. This is the exact graph mandated by ADR-0018.
     *
     * @return array<string, list<ExecutionState>> Keyed by the source state's backing value.
     */
    public static function allowedTransitions(): array
    {
        return [
            ExecutionState::Pending->value => [
                ExecutionState::Planning,
                ExecutionState::Cancelled,
            ],
            ExecutionState::Planning->value => [
                ExecutionState::Assigned,
                ExecutionState::Failed,
                ExecutionState::Cancelled,
            ],
            ExecutionState::Assigned->value => [
                ExecutionState::Running,
                ExecutionState::Waiting,
                ExecutionState::Cancelled,
            ],
            ExecutionState::Waiting->value => [
                ExecutionState::Running,
                ExecutionState::Cancelled,
                ExecutionState::Failed,
            ],
            ExecutionState::Running->value => [
                ExecutionState::Review,
                ExecutionState::Retrying,
                ExecutionState::Completed,
                ExecutionState::Failed,
                ExecutionState::Waiting,
                ExecutionState::Cancelled,
            ],
            ExecutionState::Retrying->value => [
                ExecutionState::Running,
                ExecutionState::Failed,
                ExecutionState::Cancelled,
            ],
            ExecutionState::Review->value => [
                ExecutionState::Approved,
                ExecutionState::Rejected,
                ExecutionState::Retrying,
            ],
            ExecutionState::Approved->value => [
                ExecutionState::Completed,
            ],
            ExecutionState::Rejected->value => [
                ExecutionState::Retrying,
                ExecutionState::Failed,
            ],
            ExecutionState::Failed->value => [
                ExecutionState::Recovered,
                ExecutionState::Cancelled,
            ],
            ExecutionState::Recovered->value => [
                ExecutionState::Running,
                ExecutionState::Planning,
            ],
            ExecutionState::Completed->value => [],
            ExecutionState::Cancelled->value => [],
        ];
    }

    /**
     * Whether moving from one state to another is legal under the transition graph.
     *
     * @param ExecutionState $from The current state.
     * @param ExecutionState $to   The proposed next state.
     */
    public static function canTransition(ExecutionState $from, ExecutionState $to): bool
    {
        foreach (self::allowedTransitions()[$from->value] as $allowed) {
            if ($allowed === $to) {
                return true;
            }
        }

        return false;
    }

    /**
     * Guard a transition, throwing when it is not permitted.
     *
     * @param ExecutionState $from The current state.
     * @param ExecutionState $to   The proposed next state.
     *
     * @throws IllegalExecutionTransition When the transition is not in the graph.
     */
    public static function assert(ExecutionState $from, ExecutionState $to): void
    {
        if (!self::canTransition($from, $to)) {
            throw IllegalExecutionTransition::between($from, $to);
        }
    }
}
