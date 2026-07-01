<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Exception;

use RuntimeException;

/**
 * Base type for every business-rule violation raised by the Execution domain.
 *
 * Extends the SPL {@see \RuntimeException} rather than a platform type so the domain layer stays
 * coupled only to PHP and the Kernel, honoring the hexagonal boundary. Each instance carries a
 * stable, dotted error code under the {@see self::CODE_PREFIX} namespace (for example
 * `EXEC.ILLEGAL_TRANSITION`) so callers, logs, and API responses can branch on the failure kind
 * without string-matching messages.
 */
class ExecutionException extends RuntimeException
{
    /**
     * The stable prefix shared by every Execution error code.
     */
    public const string CODE_PREFIX = 'EXEC';

    /**
     * @param string $errorCode The stable, dotted error code (e.g. `EXEC.ILLEGAL_TRANSITION`).
     * @param string $message   A human-readable description of the violation.
     */
    public function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * The stable, dotted error code identifying the kind of violation.
     */
    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
