<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal PSR-3-style file logger writing newline-delimited entries to
 * storage/logs/app-YYYY-MM-DD.log.
 */
final class Logger
{
    public function __construct(private readonly string $logPath)
    {
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        if (! is_dir($this->logPath)) {
            @mkdir($this->logPath, 0775, true);
        }

        if (! is_dir($this->logPath) || ! is_writable($this->logPath)) {
            return;
        }

        $file = $this->logPath . '/app-' . date('Y-m-d') . '.log';
        $line = sprintf(
            "[%s] %s: %s %s%s",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context === [] ? '' : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            PHP_EOL
        );

        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
