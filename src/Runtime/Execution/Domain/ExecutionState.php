<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain;

/**
 * The lifecycle state of an {@see Execution}, per ADR-0018 (execution state machine).
 *
 * Every execution is driven through exactly these thirteen states; the legal moves between them are
 * defined by {@see ExecutionStateMachine}. String-backed for stable persistence in the executions
 * table, the timeline, and read models. Two states are terminal — {@see self::Completed} and
 * {@see self::Cancelled} — and admit no further transitions.
 */
enum ExecutionState: string
{
    /** Created and validated; not yet planned. */
    case Pending = 'pending';

    /** The manager is planning the work (deciding steps/workers). */
    case Planning = 'planning';

    /** Work steps have been assigned but not yet started. */
    case Assigned = 'assigned';

    /** Paused, awaiting an external signal or an in-flight dependency. */
    case Waiting = 'waiting';

    /** Actively executing assigned steps. */
    case Running = 'running';

    /** A failure occurred and a retry is being prepared under the retry policy. */
    case Retrying = 'retrying';

    /** Held for human or manager review before it may complete. */
    case Review = 'review';

    /** Reviewed and approved; ready to complete. */
    case Approved = 'approved';

    /** Reviewed and rejected; awaiting retry or failure. */
    case Rejected = 'rejected';

    /** Terminated before completion by an explicit actor. Terminal. */
    case Cancelled = 'cancelled';

    /** Finished successfully. Terminal. */
    case Completed = 'completed';

    /** Ended in failure (retries exhausted or unrecoverable). */
    case Failed = 'failed';

    /** Reconstructed from the event store after a crash and made resumable. */
    case Recovered = 'recovered';

    /**
     * Whether this state is terminal (admits no outgoing transition).
     *
     * The terminal states are {@see self::Completed} and {@see self::Cancelled}.
     */
    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }
}
