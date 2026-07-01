<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application\Exception;

use RuntimeException;

/**
 * Raised by the Execution application layer when an execution use case cannot be carried out.
 *
 * These are orchestration-level failures — a referenced execution does not exist, a lock on an
 * execution could not be acquired, or the pipeline could not drive an execution to a resumable state
 * — as opposed to domain invariant violations (which surface as
 * {@see \Nizam\Runtime\Execution\Domain\Exception\ExecutionException}). Each instance carries a
 * stable, dotted error code under the {@see self::CODE_PREFIX} namespace so callers can branch on the
 * failure kind without matching on messages. It extends the SPL {@see \RuntimeException} to keep the
 * application layer coupled only to PHP, the Kernel, and its own domain.
 */
final class ExecutionApplicationException extends RuntimeException
{
    /**
     * The stable prefix shared by every Execution application error code.
     */
    public const string CODE_PREFIX = 'EXEC.APPLICATION';

    /**
     * @param string $errorCode The stable, dotted error code.
     * @param string $message   A human-readable description of the failure.
     */
    private function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * The lock protecting an execution could not be acquired (another run holds it).
     */
    public static function lockUnavailable(string $executionId): self
    {
        return new self(
            self::CODE_PREFIX . '.LOCK_UNAVAILABLE',
            sprintf(
                'The execution "%s" is already being processed; its lock could not be acquired.',
                $executionId,
            ),
        );
    }

    /**
     * No execution with the referenced identity exists (for the acting tenant).
     */
    public static function executionNotFound(string $executionId): self
    {
        return new self(
            self::CODE_PREFIX . '.EXECUTION_NOT_FOUND',
            sprintf('No execution "%s" exists.', $executionId),
        );
    }

    /**
     * The event store held no events for the referenced execution, so it cannot be rebuilt.
     */
    public static function noEventsToReplay(string $executionId): self
    {
        return new self(
            self::CODE_PREFIX . '.NO_EVENTS_TO_REPLAY',
            sprintf('The execution "%s" has no recorded events to replay.', $executionId),
        );
    }

    /**
     * Recovery was requested for an execution that is not in a recoverable (failed) state.
     */
    public static function notRecoverable(string $executionId, string $state): self
    {
        return new self(
            self::CODE_PREFIX . '.NOT_RECOVERABLE',
            sprintf(
                'The execution "%s" cannot be recovered from state "%s"; only failed executions are recoverable.',
                $executionId,
                $state,
            ),
        );
    }

    /**
     * The pipeline encountered a stage that produced no actionable outcome, halting the run.
     */
    public static function pipelineStalled(string $executionId, string $stage): self
    {
        return new self(
            self::CODE_PREFIX . '.PIPELINE_STALLED',
            sprintf('The execution "%s" stalled at the "%s" stage.', $executionId, $stage),
        );
    }

    /**
     * The stable, dotted error code identifying the kind of failure.
     */
    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
