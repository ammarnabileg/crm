<?php

declare(strict_types=1);

namespace HaHireAI\Core\Logging;

use HaHireAI\Core\Contracts\Logger as LoggerContract;

/**
 * A minimal, extensible file logger with PSR-3 levels. Lines are appended to
 * storage/logs/app.log; messages below the configured threshold are dropped.
 * Secrets and PII MUST NOT be logged (see docs/SECURITY_GUIDE.md).
 */
final class Logger implements LoggerContract
{
    /** PSR-3 severities, low → high. */
    private const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5,
        'alert' => 6,
        'emergency' => 7,
    ];

    private int $threshold;

    public function __construct(
        private readonly string $directory,
        string $minLevel = 'debug',
    ) {
        $this->threshold = self::LEVELS[strtolower($minLevel)] ?? 0;
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);
        $weight = self::LEVELS[$level] ?? 0;

        if ($weight < $this->threshold) {
            return;
        }

        $line = sprintf(
            '[%s] %s: %s%s%s',
            gmdate('Y-m-d\TH:i:s\Z'),
            strtoupper($level),
            $message,
            $context === [] ? '' : ' ' . $this->encodeContext($context),
            PHP_EOL,
        );

        $this->write($line);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    private function encodeContext(array $context): string
    {
        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $json === false ? '{}' : $json;
    }

    private function write(string $line): void
    {
        if (! is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }

        @file_put_contents($this->directory . '/app.log', $line, FILE_APPEND | LOCK_EX);
    }
}
