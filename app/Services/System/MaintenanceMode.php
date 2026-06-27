<?php

declare(strict_types=1);

namespace App\Services\System;

/**
 * Dashboard-driven maintenance switch, backed by a single JSON flag file under
 * storage/framework. No terminal required: the System UI toggles it and the
 * CheckMaintenanceMode middleware reads it on every request.
 *
 * Reads and writes are deliberately defensive — a missing, unreadable or
 * malformed flag file simply means "maintenance disabled" rather than an error,
 * so a half-written file can never lock super admins out of the dashboard.
 */
final class MaintenanceMode
{
    /**
     * Whether maintenance mode is currently active.
     */
    public function isEnabled(): bool
    {
        return ($this->read()['enabled'] ?? false) === true;
    }

    /**
     * Turn maintenance mode on, recording an optional banner message and an
     * optional IP allowed through while the site is down. Re-enabling preserves
     * the original "since" timestamp.
     */
    public function enable(string $message = '', ?string $allowIp = null): void
    {
        $current = $this->read();
        $since = (is_array($current) && ($current['enabled'] ?? false) === true && ! empty($current['since']))
            ? (string) $current['since']
            : now();

        $this->write([
            'enabled'  => true,
            'message'  => $message,
            'since'    => $since,
            'allow_ip' => ($allowIp !== null && $allowIp !== '') ? $allowIp : null,
        ]);
    }

    /**
     * Turn maintenance mode off by clearing the flag file's payload.
     */
    public function disable(): void
    {
        $this->write([
            'enabled'  => false,
            'message'  => '',
            'since'    => null,
            'allow_ip' => null,
        ]);
    }

    /**
     * The visitor-facing message, or an empty string when none was set.
     */
    public function message(): string
    {
        return (string) ($this->read()['message'] ?? '');
    }

    /**
     * The full normalised state used by the UI and middleware.
     *
     * @return array{enabled:bool,message:string,since:?string,allow_ip:?string}
     */
    public function details(): array
    {
        $data = $this->read();

        return [
            'enabled'  => ($data['enabled'] ?? false) === true,
            'message'  => (string) ($data['message'] ?? ''),
            'since'    => isset($data['since']) && $data['since'] !== '' ? (string) $data['since'] : null,
            'allow_ip' => isset($data['allow_ip']) && $data['allow_ip'] !== '' ? (string) $data['allow_ip'] : null,
        ];
    }

    /**
     * Absolute path to the JSON flag file.
     */
    public function path(): string
    {
        return storage_path('framework/maintenance.json');
    }

    /**
     * Read and decode the flag file, returning an empty array on any problem.
     *
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $path = $this->path();
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Encode and persist the flag file, creating its directory if needed.
     *
     * @param array<string, mixed> $data
     */
    private function write(array $data): void
    {
        $path = $this->path();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }

        @file_put_contents($path, $json, LOCK_EX);
    }
}
