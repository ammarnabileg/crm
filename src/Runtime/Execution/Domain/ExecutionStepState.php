<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain;

/**
 * The small lifecycle state of a single {@see ExecutionStep}.
 *
 * A step is distinct from the whole {@see Execution}: it tracks only its own dispatch to a worker.
 * String-backed for stable persistence in the worker-results/step rows and read models.
 */
enum ExecutionStepState: string
{
    /** Assigned to a worker but not yet started. */
    case Pending = 'pending';

    /** Currently being executed by its worker. */
    case Running = 'running';

    /** Finished successfully with a worker result. */
    case Completed = 'completed';

    /** Finished with errors (either the worker reported errors or dispatch failed). */
    case Failed = 'failed';
}
