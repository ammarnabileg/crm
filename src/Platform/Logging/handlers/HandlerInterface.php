<?php

declare(strict_types=1);

namespace Nizam\Platform\Logging\handlers;

/**
 * A sink that persists a single, already-formatted log record.
 *
 * The {@see \Nizam\Platform\Logging\Logger} builds a structured record (level, message, context,
 * channel, timestamp) and hands it to each configured handler. Handlers decide where and how the
 * record is written (a JSON-line stream, a null sink, etc.). Keeping this contract narrow lets the
 * logger stay transport-agnostic.
 */
interface HandlerInterface
{
    /**
     * Persist one log record.
     *
     * @param array{
     *     level: string,
     *     message: string,
     *     context: array<string, mixed>,
     *     channel: string,
     *     timestamp: string
     * } $record
     */
    public function handle(array $record): void;
}
