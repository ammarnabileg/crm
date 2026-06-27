<?php

declare(strict_types=1);

namespace HaHireAI\Core\Errors;

use ErrorException;
use HaHireAI\Core\Contracts\Logger;
use HaHireAI\Core\Http\Response;
use Throwable;

/**
 * Global error & exception handler. In production it NEVER leaks stack traces
 * to the client; in development it renders detail to aid debugging. Feeds the
 * Phase-15 error tracker via the logger. See docs/ERROR_HANDLING_GUIDE.md.
 */
final class ErrorHandler
{
    public function __construct(
        private readonly bool $debug,
        private readonly ?Logger $logger = null,
    ) {
    }

    public function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $this->debug ? '1' : '0');

        set_error_handler($this->handleError(...));
        set_exception_handler($this->handleException(...));
        register_shutdown_function($this->handleShutdown(...));
    }

    public function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if ((error_reporting() & $level) === 0) {
            return false;
        }

        throw new ErrorException($message, 0, $level, $file, $line);
    }

    public function handleException(Throwable $e): void
    {
        $this->logger?->error($e->getMessage(), [
            'exception' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $this->renderCli($e));

            return;
        }

        $this->toResponse($e)->send();
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $this->handleException(new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line'],
            ));
        }
    }

    /** Convert a throwable into an HTTP response without leaking detail in prod. */
    public function toResponse(Throwable $e): Response
    {
        $status = $this->statusFor($e);

        if ($this->debug) {
            $body = sprintf(
                "%s: %s\nin %s:%d\n\n%s",
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString(),
            );

            return Response::text($body, $status);
        }

        $message = match ($status) {
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Unprocessable Entity',
            default => 'Server Error',
        };

        return Response::html("<h1>{$status}</h1><p>{$message}</p>", $status);
    }

    private function statusFor(Throwable $e): int
    {
        $code = $e->getCode();

        return is_int($code) && $code >= 400 && $code <= 599 ? $code : 500;
    }

    private function renderCli(Throwable $e): string
    {
        $base = sprintf("[%s] %s in %s:%d%s", $e::class, $e->getMessage(), $e->getFile(), $e->getLine(), PHP_EOL);

        return $this->debug ? $base . $e->getTraceAsString() . PHP_EOL : $base;
    }
}
