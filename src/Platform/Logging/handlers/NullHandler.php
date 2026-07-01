<?php

declare(strict_types=1);

namespace Nizam\Platform\Logging\handlers;

/**
 * A handler that discards every record.
 *
 * Useful as a default channel sink, in tests, or wherever logging must be a no-op without
 * special-casing the logger. Implements the null-object pattern for {@see HandlerInterface}.
 */
final class NullHandler implements HandlerInterface
{
    /**
     * Discard the record.
     *
     * @param array{level: string, message: string, context: array<string, mixed>, channel: string, timestamp: string} $record
     */
    public function handle(array $record): void
    {
        // Intentionally does nothing.
    }
}
