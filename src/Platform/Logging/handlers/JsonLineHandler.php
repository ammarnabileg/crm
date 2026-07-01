<?php

declare(strict_types=1);

namespace Nizam\Platform\Logging\handlers;

use Nizam\Platform\Exception\PlatformException;
use Nizam\Platform\Support\Json;

/**
 * Writes each log record as a single JSON object on its own line (newline-delimited JSON).
 *
 * This "JSON Lines" format is trivially machine-parseable and is the platform's default on-disk
 * and streaming log format. The handler can target either an open stream resource or a file path
 * (opened lazily in append mode on first write). Each line contains the timestamp, level, channel,
 * message, and structured context.
 */
final class JsonLineHandler implements HandlerInterface
{
    /**
     * An open stream resource to write to, or null when writing to a path.
     *
     * @var resource|null
     */
    private $stream;

    /**
     * Whether this handler owns (and must close) the stream it opened from a path.
     */
    private bool $ownsStream = false;

    /**
     * @param resource|string $target Either an open stream resource or a writable file path.
     *
     * @throws PlatformException When the target is neither a resource nor a string.
     */
    public function __construct(private readonly mixed $target)
    {
        if (is_resource($target)) {
            $this->stream = $target;
        } elseif (!is_string($target)) {
            throw new PlatformException('JsonLineHandler target must be a stream resource or a file path.');
        } else {
            $this->stream = null;
        }
    }

    /**
     * Close the stream if this handler opened it.
     */
    public function __destruct()
    {
        if ($this->ownsStream && is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    /**
     * Append the record to the target as one JSON line.
     *
     * @param array{level: string, message: string, context: array<string, mixed>, channel: string, timestamp: string} $record
     *
     * @throws PlatformException When the target cannot be opened or written.
     */
    public function handle(array $record): void
    {
        $line = Json::encode([
            'timestamp' => $record['timestamp'],
            'level' => $record['level'],
            'channel' => $record['channel'],
            'message' => $record['message'],
            'context' => $record['context'],
        ]) . "\n";

        $stream = $this->resolveStream();

        if (fwrite($stream, $line) === false) {
            throw new PlatformException('Failed to write a log record to the target stream.');
        }
    }

    /**
     * Return the open stream, opening the configured path on first use.
     *
     * @return resource
     *
     * @throws PlatformException When the path cannot be opened for appending.
     */
    private function resolveStream()
    {
        if (is_resource($this->stream)) {
            return $this->stream;
        }

        /** @var string $path */
        $path = $this->target;
        $handle = @fopen($path, 'a');

        if ($handle === false) {
            throw new PlatformException(sprintf('Unable to open log file "%s" for writing.', $path));
        }

        $this->stream = $handle;
        $this->ownsStream = true;

        return $handle;
    }
}
