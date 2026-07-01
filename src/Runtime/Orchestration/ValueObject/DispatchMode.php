<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\ValueObject;

/**
 * How the {@see \Nizam\Runtime\Orchestration\WorkerCoordinator} dispatches a batch of worker tasks.
 *
 * The three modes are the spec-mandated coordination strategies. {@see self::Sequential} runs tasks
 * one after another in order. {@see self::Parallel} models a deterministic in-process fan-out: tasks
 * are independent and their results collected in submission order — there are no real threads, so the
 * outcome is reproducible. {@see self::Collaborative} allows a worker to ask for additional workers,
 * which the Runtime satisfies by re-entering the coordinator (never worker-to-worker directly).
 * String-backed for stable persistence and transport.
 */
enum DispatchMode: string
{
    /** Run tasks strictly in order, one at a time. */
    case Sequential = 'sequential';

    /** Fan tasks out as independent invocations, collecting results in submission order. */
    case Parallel = 'parallel';

    /** Run tasks and let them request further workers by re-entering the coordinator. */
    case Collaborative = 'collaborative';
}
