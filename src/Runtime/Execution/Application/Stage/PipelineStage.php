<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application\Stage;

use Nizam\Runtime\Execution\Application\PipelineContext;

/**
 * One stage in the {@see \Nizam\Runtime\Execution\Application\ExecutionPipeline}.
 *
 * The pipeline drives an execution through an ordered sequence of stages — Validate, Plan, Assign,
 * Run, Review, Finalize — each of which advances the execution one legal step through its state
 * machine by mutating the aggregate carried on the {@see PipelineContext}. A stage does exactly one
 * transition-worth of work and never persists or publishes; the engine owns the transaction boundary.
 * A stage that cannot legally act on the current state must raise (the aggregate's own guards enforce
 * legality), letting the pipeline short-circuit the execution to Failed or Recovered.
 */
interface PipelineStage
{
    /**
     * A stable, human-readable name for the stage (used in timelines and error messages).
     */
    public function name(): string;

    /**
     * Advance the execution one stage by mutating the aggregate on the context.
     *
     * @param PipelineContext $context The request-scoped carrier holding the live execution.
     */
    public function process(PipelineContext $context): void;
}
