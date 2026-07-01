<?php

declare(strict_types=1);

namespace Nizam\Platform\Logging;

use DateTimeImmutable;
use Nizam\Kernel\Domain\Clock;
use Nizam\Platform\Logging\handlers\HandlerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException as PsrInvalidArgumentException;
use Psr\Log\LogLevel;
use Stringable;

/**
 * A PSR-3 logger that formats records and fans them out to one or more handlers.
 *
 * Responsibilities:
 *   - validate the PSR-3 log level,
 *   - interpolate `{placeholder}` tokens in the message from the context array (PSR-3 §1.2),
 *   - stamp each record with an ISO-8601 timestamp from the injected {@see Clock}, and
 *   - build the canonical record shape and pass it to every handler.
 *
 * Extends {@see AbstractLogger}, so the eight level-specific methods (`info()`, `error()`, …)
 * funnel through {@see self::log()}.
 */
final class Logger extends AbstractLogger
{
    private const LEVELS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];

    /**
     * @param string                        $channel  Logical channel name (app, security, …).
     * @param array<int, HandlerInterface>  $handlers Sinks the record is written to.
     * @param Clock                         $clock    Time source for record timestamps.
     */
    public function __construct(
        private readonly string $channel,
        private readonly array $handlers,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Log a message at the given level (PSR-3).
     *
     * @param mixed                $level
     * @param array<string, mixed> $context
     *
     * @throws PsrInvalidArgumentException When $level is not a valid PSR-3 level.
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!is_string($level) || !in_array($level, self::LEVELS, true)) {
            throw new PsrInvalidArgumentException(sprintf('Invalid log level "%s".', is_scalar($level) ? (string) $level : gettype($level)));
        }

        $interpolated = $this->interpolate((string) $message, $context);

        $record = [
            'level' => $level,
            'message' => $interpolated,
            'context' => $context,
            'channel' => $this->channel,
            'timestamp' => $this->timestamp(),
        ];

        foreach ($this->handlers as $handler) {
            $handler->handle($record);
        }
    }

    /**
     * The channel this logger writes under.
     */
    public function channel(): string
    {
        return $this->channel;
    }

    /**
     * Substitute `{key}` tokens in the message with stringable context values (PSR-3 §1.2).
     *
     * @param array<string, mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        if (!str_contains($message, '{')) {
            return $message;
        }

        $replacements = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null || $value instanceof Stringable) {
                $replacements['{' . $key . '}'] = $value === null ? 'null' : (string) $value;
            }
        }

        return strtr($message, $replacements);
    }

    /**
     * The current time as an ISO-8601 string with microseconds.
     */
    private function timestamp(): string
    {
        $now = $this->clock->now();

        return $now->format(DateTimeImmutable::RFC3339_EXTENDED);
    }
}
