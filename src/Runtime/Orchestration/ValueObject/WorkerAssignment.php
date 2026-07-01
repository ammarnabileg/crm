<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\ValueObject;

use Nizam\Platform\Plugin\Contract\WorkerPlugin;

/**
 * One resolved worker bound to the task it will run — the unit the
 * {@see \Nizam\Runtime\Orchestration\WorkerCoordinator} dispatches.
 *
 * The coordinator never receives bare tasks: the orchestrator resolves each {@see WorkTask}'s worker
 * reference to a concrete {@see WorkerPlugin} first, so authorization and availability are settled before
 * dispatch. Pairing the plugin with its task here keeps the coordinator's dispatch loop simple and makes
 * it structurally impossible for a worker to be invoked on a task it was not assigned. This is a small,
 * behavior-free carrier object rather than a persisted value object — it holds a live plugin instance —
 * so it deliberately does not implement the {@see \Nizam\Kernel\Domain\ValueObject} contract.
 */
final class WorkerAssignment
{
    /**
     * @param WorkerPlugin $worker The resolved worker plugin to invoke.
     * @param WorkTask     $task   The task the worker will perform.
     */
    public function __construct(
        private readonly WorkerPlugin $worker,
        private readonly WorkTask $task,
    ) {
    }

    /**
     * The resolved worker plugin to invoke.
     */
    public function worker(): WorkerPlugin
    {
        return $this->worker;
    }

    /**
     * The task the worker will perform.
     */
    public function task(): WorkTask
    {
        return $this->task;
    }
}
