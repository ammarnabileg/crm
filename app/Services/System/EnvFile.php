<?php

declare(strict_types=1);

namespace App\Services\System;

use RuntimeException;

/**
 * Read, parse and safely rewrite the project .env file from the dashboard.
 *
 * The Setup & Installer Bible requires that an admin can adjust safe runtime
 * settings from the browser instead of hand-editing .env over SSH. This service
 * is the careful bit underneath the Environment Editor: it preserves the file's
 * existing order, blank-line grouping and comments verbatim, only touching the
 * specific keys it is asked to change, and writes atomically (temp file + rename)
 * so a failure can never leave a half-written or blanked-out .env behind.
 */
final class EnvFile
{
    /**
     * New keys are only ever appended when they appear on this whitelist. Any
     * key already present in the file may always be updated in place; this list
     * just bounds what the editor is allowed to *introduce*.
     *
     * @var string[]
     */
    private const APPENDABLE = [
        'APP_NAME',
        'APP_URL',
        'APP_DEBUG',
        'APP_LOCALE',
        'APP_FALLBACK_LOCALE',
        'APP_TIMEZONE',
        'APP_CURRENCY',
        'ASSET_VERSION',
        'MAIL_ENABLED',
        'MAIL_FROM_ADDRESS',
        'MAIL_FROM_NAME',
        'SESSION_SECURE',
    ];

    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? base_path('.env');
    }

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * Parse the file into an associative array of KEY => value.
     *
     * Blank lines and comments are ignored, surrounding quotes are stripped and
     * (for double-quoted values) the same escape sequences the loader honours
     * are decoded, so what you read back matches the running configuration.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $values = [];
        foreach ($this->readLines() as $line) {
            $parsed = $this->parseLine($line);
            if ($parsed !== null) {
                [$key, $value] = $parsed;
                $values[$key] = $value;
            }
        }

        return $values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * Update the given keys, preserving everything else verbatim.
     *
     * Keys already present in the file are rewritten in place (keeping their
     * position and surrounding layout); whitelisted keys that are absent are
     * appended at the end. Unknown keys that are not already present are ignored
     * for safety. Values containing whitespace, '#' or quote characters are
     * re-quoted so the file stays parseable. The write is atomic and aborts
     * without truncating the existing file on any failure.
     *
     * @param array<string, scalar|null> $values
     */
    public function set(array $values): void
    {
        if ($values === []) {
            return;
        }

        $lines = $this->readLines();
        $seen = [];

        foreach ($lines as $index => $line) {
            $key = $this->lineKey($line);
            if ($key === null || ! array_key_exists($key, $values)) {
                continue;
            }

            // Only the first occurrence of a key is authoritative; leave any
            // accidental duplicates untouched rather than silently diverging.
            if (isset($seen[$key])) {
                continue;
            }

            $lines[$index] = $key . '=' . $this->encode((string) ($values[$key] ?? ''));
            $seen[$key] = true;
        }

        // Append whitelisted keys that did not already exist in the file.
        foreach ($values as $key => $value) {
            if (isset($seen[$key]) || ! in_array($key, self::APPENDABLE, true)) {
                continue;
            }
            $lines[] = $key . '=' . $this->encode((string) ($value ?? ''));
            $seen[$key] = true;
        }

        $this->writeAtomically(implode("\n", $lines) . "\n");
    }

    /**
     * @return string[]
     */
    private function readLines(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $contents = @file_get_contents($this->path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read the .env file at ' . $this->path . '.');
        }

        // Normalise line endings, then split. A trailing newline produces an
        // empty final element which we drop so re-joining doesn't grow the file.
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);
        $lines = explode("\n", $contents);
        if ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * Extract the KEY of an assignment line, or null for blanks/comments/other.
     */
    private function lineKey(string $line): ?string
    {
        $parsed = $this->parseLine($line);

        return $parsed[0] ?? null;
    }

    /**
     * Parse a single line into [key, value] or null when it is not an
     * assignment (blank line, comment, or malformed).
     *
     * @return array{0:string,1:string}|null
     */
    private function parseLine(string $line): ?array
    {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            return null;
        }

        if (str_starts_with($trimmed, 'export ')) {
            $trimmed = substr($trimmed, 7);
        }

        if (! str_contains($trimmed, '=')) {
            return null;
        }

        [$name, $value] = explode('=', $trimmed, 2);
        $name = trim($name);
        if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $name) !== 1) {
            return null;
        }

        return [$name, $this->decode(trim($value))];
    }

    /**
     * Strip surrounding quotes and decode escapes, mirroring the loader so a
     * round-trip through the editor is lossless.
     */
    private function decode(string $value): string
    {
        // Drop a trailing inline comment on unquoted values (e.g. `foo # note`).
        if ($value !== '' && $value[0] !== '"' && $value[0] !== "'") {
            $hashPos = strpos($value, ' #');
            if ($hashPos !== false) {
                $value = rtrim(substr($value, 0, $hashPos));
            }
        }

        $length = strlen($value);
        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
                if ($first === '"') {
                    $value = str_replace(['\\n', '\\r', '\\"', '\\\\'], ["\n", "\r", '"', '\\'], $value);
                }
            }
        }

        return $value;
    }

    /**
     * Encode a value for writing, quoting only when needed to stay parseable.
     */
    private function encode(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/\s|#|"|\'/', $value) === 1) {
            return '"' . str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value) . '"';
        }

        return $value;
    }

    /**
     * Write contents to a temp file and atomically rename over the target, so a
     * reader never sees a partial file and a failure leaves the original intact.
     */
    private function writeAtomically(string $contents): void
    {
        $dir = dirname($this->path);
        if (! is_dir($dir) || ! is_writable($dir)) {
            throw new RuntimeException('The project root is not writable; cannot update .env.');
        }
        if (is_file($this->path) && ! is_writable($this->path)) {
            throw new RuntimeException('The .env file is not writable.');
        }

        $temp = @tempnam($dir, '.env');
        if ($temp === false) {
            throw new RuntimeException('Unable to create a temporary file to update .env.');
        }

        if (@file_put_contents($temp, $contents, LOCK_EX) === false) {
            @unlink($temp);
            throw new RuntimeException('Unable to write the temporary .env file.');
        }

        // Preserve sensible permissions for a secrets file before swapping.
        @chmod($temp, 0644);

        if (! @rename($temp, $this->path)) {
            @unlink($temp);
            throw new RuntimeException('Unable to replace the .env file. Make sure the project root is writable.');
        }

        // Best-effort: drop any stale opcache entry for the file's directory.
        clearstatcache(true, $this->path);
    }
}
