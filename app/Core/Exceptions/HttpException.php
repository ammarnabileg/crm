<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception that carries an HTTP status code, used by abort() and the router
 * to render the appropriate error page.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
