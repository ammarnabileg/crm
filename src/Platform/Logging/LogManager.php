<?php

declare(strict_types=1);

namespace Nizam\Platform\Logging;

use Nizam\Kernel\Domain\Clock;
use Nizam\Platform\Logging\handlers\HandlerInterface;
use Nizam\Platform\Logging\handlers\NullHandler;
use Psr\Log\LoggerInterface;

/**
 * Creates and caches one {@see Logger} per logical channel.
 *
 * The platform separates logs by concern into named channels — `app`, `activity`,
 * `performance`, `error`, and `security` are always available — each with its own set of
 * handlers. Callers resolve a channel via {@see self::channel()} and get a PSR-3
 * {@see LoggerInterface} back. Unknown channels fall back to the default handler set, so a typo
 * never loses a log line.
 */
final class LogManager
{
    /**
     * The always-available channel names.
     *
     * @var array<int, string>
     */
    public const CHANNELS = ['app', 'activity', 'performance', 'error', 'security'];

    /**
     * Per-channel handler sets keyed by channel name.
     *
     * @var array<string, array<int, HandlerInterface>>
     */
    private array $channelHandlers;

    /**
     * Resolved loggers keyed by channel name.
     *
     * @var array<string, Logger>
     */
    private array $loggers = [];

    /**
     * @param Clock                                                $clock          Time source shared by every logger.
     * @param array<int, HandlerInterface>                         $defaultHandlers Handlers used by channels without an override.
     * @param array<string, array<int, HandlerInterface>>          $channelHandlers Per-channel handler overrides.
     */
    public function __construct(
        private readonly Clock $clock,
        private readonly array $defaultHandlers = [],
        array $channelHandlers = [],
    ) {
        $this->channelHandlers = $channelHandlers;
    }

    /**
     * Get (creating on first use) the logger for a channel.
     */
    public function channel(string $name = 'app'): LoggerInterface
    {
        if (isset($this->loggers[$name])) {
            return $this->loggers[$name];
        }

        $handlers = $this->channelHandlers[$name] ?? $this->defaultHandlers;

        if ($handlers === []) {
            $handlers = [new NullHandler()];
        }

        return $this->loggers[$name] = new Logger($name, $handlers, $this->clock);
    }

    /**
     * Register or replace the handler set for a specific channel.
     *
     * @param array<int, HandlerInterface> $handlers
     */
    public function setChannelHandlers(string $name, array $handlers): void
    {
        $this->channelHandlers[$name] = $handlers;
        unset($this->loggers[$name]);
    }
}
