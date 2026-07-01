<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\ValueObject;

/**
 * The outcome a Manager plugin reaches after reviewing merged worker results.
 *
 * A manager never talks to the user or to workers directly; it evaluates the {@see MergedResult} and
 * the {@see ConfidenceAssessment} the Runtime hands it and returns exactly one of these outcomes, which
 * the {@see \Nizam\Runtime\Orchestration\MasterOrchestrator} projects into the execution's terminal
 * shape. String-backed for stable persistence in the `manager_decisions` table and transport.
 */
enum DecisionOutcome: string
{
    /** The manager accepts the merged result; the execution may complete. */
    case Approved = 'approved';

    /** The manager rejects the merged result; the work did not meet the bar. */
    case Rejected = 'rejected';

    /** The manager wants the work retried under the execution's retry policy. */
    case RetryRequested = 'retry_requested';

    /** The manager wants additional workers dispatched (re-entering the coordinator). */
    case MoreWorkersRequested = 'more_workers_requested';

    /** The manager cannot decide and escalates to a human or a higher authority. */
    case Escalated = 'escalated';

    /**
     * Whether this outcome accepts the work as done.
     */
    public function isApproval(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Whether this outcome asks the Runtime to do more work (retry or more workers).
     */
    public function requestsMoreWork(): bool
    {
        return $this === self::RetryRequested || $this === self::MoreWorkersRequested;
    }
}
