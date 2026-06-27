<?php

declare(strict_types=1);

namespace App\Services\System;

/**
 * Dashboard-driven reader for the application log files under storage/logs.
 *
 * Everything an admin needs to triage problems happens in the browser: list the
 * log files, tail the most recent lines, see each line classified by level and —
 * for errors and warnings — a plain-English suggested fix, and truncate a log
 * when it has been dealt with. No terminal, no `tail -f`, no SSH.
 *
 * Reads are deliberately defensive (a missing/unreadable file simply yields an
 * empty result) and every file name is resolved strictly under storage/logs so a
 * crafted "../" name can never escape the log directory.
 */
final class LogReader
{
    /**
     * Map of message patterns to a human, actionable suggested fix. Ordered by
     * specificity; the first match wins. Kept small on purpose — it covers the
     * handful of failures an admin actually hits right after install.
     *
     * @var array<string, string>
     */
    private const FIXES = [
        '/SQLSTATE\[42S02\]|doesn\'t exist|Unknown column|Base table or view not found/i'
            => 'A table/column is missing — re-run migrations from Setup.',
        '/Connection refused|SQLSTATE\[HY000\] \[2002\]|\b2002\b|could not find driver|Access denied for user/i'
            => 'Database is unreachable — check DB host/port/credentials in the Environment editor.',
        '/Permission denied|not writable|failed to open stream: Permission|could not be opened in append mode/i'
            => 'A storage folder isn\'t writable — run Permissions Auto-Fix in Setup.',
        '/Allowed memory size|memory_limit|Out of memory/i'
            => 'PHP memory_limit is too low — raise it in php.ini / hosting panel.',
        '/\bZIP\b|Class .?Imagick.? not found|Class .?ZipArchive.? not found|extension|undefined function (?:gd|mb|curl|openssl|intl)/i'
            => 'A PHP extension is missing — enable it in your hosting panel.',
    ];

    /**
     * List every *.log file in storage/logs, newest first.
     *
     * @return array<int, array{name:string, size:int, modified:int}>
     */
    public function files(): array
    {
        $dir = $this->dir();
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach ((glob($dir . '/*.log') ?: []) as $path) {
            if (! is_file($path)) {
                continue;
            }

            $files[] = [
                'name'     => basename($path),
                'size'     => (int) (@filesize($path) ?: 0),
                'modified' => (int) (@filemtime($path) ?: 0),
            ];
        }

        usort($files, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return $files;
    }

    /**
     * Return the last $lines parsed entries of a named log file (newest at the
     * bottom, the way a console shows them). Each entry is best-effort parsed
     * into level / time / message; unparseable lines fall back to level "info".
     *
     * @return array<int, array{level:string, time:?string, message:string}>
     */
    public function tail(string $name, int $lines = 300): array
    {
        $path = $this->resolve($name);
        if ($path === null || ! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $lines = max(1, $lines);
        $raw = $this->readLastLines($path, $lines);

        $entries = [];
        foreach ($raw as $line) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }
            $entries[] = $this->parseLine($line);
        }

        return $entries;
    }

    /**
     * Suggest a fix for a log message, or null when nothing in the map matches.
     */
    public function classify(string $message): ?string
    {
        foreach (self::FIXES as $pattern => $fix) {
            if (preg_match($pattern, $message) === 1) {
                return $fix;
            }
        }

        return null;
    }

    /**
     * Truncate a named log file to zero bytes. A missing file is a no-op; the
     * path is resolved strictly under storage/logs.
     */
    public function clear(string $name): void
    {
        $path = $this->resolve($name);
        if ($path === null || ! is_file($path) || ! is_writable($path)) {
            return;
        }

        @file_put_contents($path, '', LOCK_EX);
    }

    /**
     * Absolute path to the log directory.
     */
    private function dir(): string
    {
        return storage_path('logs');
    }

    /**
     * Resolve a user-supplied log name to an absolute path that is guaranteed to
     * sit directly inside storage/logs and end in ".log". Returns null for any
     * traversal attempt, sub-path, or non-log name. The file need not yet exist
     * (so the path can still be used to truncate), but its directory must.
     */
    private function resolve(string $name): ?string
    {
        // Reject anything that isn't a bare file name.
        $base = basename($name);
        if ($base === '' || $base !== $name || $base === '.' || $base === '..') {
            return null;
        }
        if (str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")) {
            return null;
        }
        if (! str_ends_with($base, '.log')) {
            return null;
        }

        $dir = $this->dir();
        $realDir = realpath($dir);
        if ($realDir === false) {
            return null;
        }

        $candidate = $realDir . DIRECTORY_SEPARATOR . $base;

        // If the file exists, its real path must still live under the log dir
        // (defends against a symlinked log name pointing elsewhere).
        $real = realpath($candidate);
        if ($real !== false && ! str_starts_with($real, $realDir . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $candidate;
    }

    /**
     * Efficiently read the last $count lines of a (possibly large) file without
     * loading the whole thing into memory, by walking backwards in chunks.
     *
     * @return array<int, string> lines in natural (top-to-bottom) order
     */
    private function readLastLines(string $path, int $count): array
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $buffer = '';
        $chunkSize = 8192;
        $newlines = 0;

        try {
            if (fseek($handle, 0, SEEK_END) !== 0) {
                return [];
            }
            $position = ftell($handle);
            if ($position === false) {
                return [];
            }

            // Walk backwards a chunk at a time, accumulating until we've seen one
            // more newline than requested (so the first wanted line is complete).
            while ($position > 0 && $newlines <= $count) {
                $read = (int) min($chunkSize, $position);
                $position -= $read;
                if (fseek($handle, $position, SEEK_SET) !== 0) {
                    break;
                }
                $chunk = (string) fread($handle, $read);
                $buffer = $chunk . $buffer;
                $newlines = substr_count($buffer, "\n");
            }
        } finally {
            fclose($handle);
        }

        $lines = preg_split('/\r\n|\n|\r/', $buffer) ?: [];

        // Drop a trailing empty element produced by a final newline.
        if ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        if (count($lines) > $count) {
            $lines = array_slice($lines, -$count);
        }

        return array_values($lines);
    }

    /**
     * Best-effort parse of a single log line into level / time / message.
     *
     * Recognises the common Monolog-style "[2026-06-27 00:58:44] ERROR: message"
     * shape (optionally with a channel like "app.ERROR"), and a bare leading
     * level token such as "WARNING something happened". Anything else is treated
     * as an info-level message verbatim.
     *
     * @return array{level:string, time:?string, message:string}
     */
    private function parseLine(string $line): array
    {
        $time = null;
        $level = null;
        $message = $line;

        // [timestamp] CHANNEL.LEVEL: message   |   [timestamp] LEVEL: message
        if (preg_match('/^\[([^\]]+)\]\s*(?:[\w-]+\.)?([A-Za-z]+)\s*:\s*(.*)$/s', $line, $m) === 1) {
            $time = trim($m[1]);
            $level = $m[2];
            $message = $m[3];
        } elseif (preg_match('/^\[([^\]]+)\]\s*(.*)$/s', $line, $m) === 1) {
            // Timestamp present but no recognised level token.
            $time = trim($m[1]);
            $message = $m[2];
            if (preg_match('/^([A-Za-z]+)\s*[:\-]\s*(.*)$/s', $message, $lm) === 1) {
                $level = $lm[1];
                $message = $lm[2];
            }
        } elseif (preg_match('/^([A-Za-z]+)\s*[:\-]\s*(.*)$/s', $line, $m) === 1) {
            // Leading bare level token, e.g. "ERROR: ..." or "WARNING - ...".
            $level = $m[1];
            $message = $m[2];
        }

        return [
            'level'   => $this->normaliseLevel($level),
            'time'    => $time,
            'message' => trim($message),
        ];
    }

    /**
     * Collapse the many synonyms a logger might emit down to one of the four
     * levels the UI knows how to colour, defaulting to "info".
     */
    private function normaliseLevel(?string $level): string
    {
        $level = strtolower(trim((string) $level));

        return match (true) {
            in_array($level, ['emergency', 'alert', 'critical', 'error', 'err', 'fatal'], true) => 'error',
            in_array($level, ['warning', 'warn'], true) => 'warning',
            in_array($level, ['debug', 'trace'], true) => 'debug',
            in_array($level, ['notice', 'info', 'informational'], true) => 'info',
            default => 'info',
        };
    }
}
