<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Port;

use Nizam\Platform\Plugin\Contract\WorkerPlugin;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;
use Nizam\Runtime\Orchestration\ValueObject\ExecutionContext;
use Nizam\Runtime\Orchestration\ValueObject\WorkTask;

/**
 * The port that actually runs a resolved {@see WorkerPlugin} against a {@see WorkTask}.
 *
 * The plugin-kind contract ({@see WorkerPlugin}) identifies *which* worker; how a worker executes a task
 * is a runtime concern the platform owns, not part of the SDK contract. The
 * {@see \Nizam\Runtime\Orchestration\WorkerCoordinator} depends only on this interface: given the
 * resolved worker plugin, the task, and the request-scoped {@see ExecutionContext}, it returns the
 * worker's self-validating {@see WorkerResult}. A worker never invokes another worker; every invocation
 * passes through this port under the coordinator. A deterministic in-memory adapter serves tests and
 * safe defaults; the production adapter drives the plugin sandbox.
 */
interface WorkerInvoker
{
    /**
     * Invoke a worker plugin on a task within the given context and return its structured result.
     *
     * @param WorkerPlugin     $worker  The resolved worker plugin to invoke.
     * @param WorkTask         $task    The task to perform.
     * @param ExecutionContext $context The request-scoped context (tenant, permissions, deadline).
     *
     * @return WorkerResult The worker's self-validating structured output.
     */
    public function invoke(WorkerPlugin $worker, WorkTask $task, ExecutionContext $context): WorkerResult;
}
