<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Observability\Application;

use HaHireAI\Core\Database\Connection;
use Throwable;

/**
 * Read-only system diagnostics for the Platform Context. Every value is a real,
 * observed fact (DB server, disk, runtime, queue) — nothing is mocked. Used by
 * the diagnostics screen to give operators an at-a-glance infrastructure view
 * (docs/OBSERVABILITY.md).
 */
final class SystemDiagnostics
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $storagePath,
        private readonly string $basePath,
    ) {
    }

    /**
     * @param  array{https?: bool, forwarded_proto?: string, host?: string}  $request
     * @return list<array{key: string, label: string, status: string, items: list<array{k: string, v: string}>}>
     */
    public function panels(array $request = []): array
    {
        return [
            $this->database(),
            $this->storage(),
            $this->cache(),
            $this->queue(),
            $this->mail(),
            $this->transport($request),
            $this->scheduler(),
            $this->runtime(),
        ];
    }

    /** @return array{key: string, label: string, status: string, items: list<array{k: string, v: string}>} */
    private function database(): array
    {
        try {
            $version = (string) ($this->connection->selectOne('SELECT VERSION() AS v')['v'] ?? 'unknown');
            $stats = $this->connection->selectOne(
                'SELECT COUNT(*) AS tables, COALESCE(SUM(data_length + index_length), 0) AS bytes
                   FROM information_schema.tables WHERE table_schema = DATABASE()',
            ) ?? [];
            $db = (string) ($this->connection->selectOne('SELECT DATABASE() AS d')['d'] ?? '');

            return $this->panel('database', 'Database', 'ok', [
                ['k' => 'Driver', 'v' => 'MySQL'],
                ['k' => 'Version', 'v' => $version],
                ['k' => 'Schema', 'v' => $db],
                ['k' => 'Tables', 'v' => (string) (int) ($stats['tables'] ?? 0)],
                ['k' => 'Size', 'v' => $this->humanBytes((int) ($stats['bytes'] ?? 0))],
                ['k' => 'Connection', 'v' => 'reachable'],
            ]);
        } catch (Throwable $e) {
            return $this->panel('database', 'Database', 'down', [
                ['k' => 'Connection', 'v' => 'unreachable'],
                ['k' => 'Error', 'v' => $e->getMessage()],
            ]);
        }
    }

    /** @return array{key: string, label: string, status: string, items: list<array{k: string, v: string}>} */
    private function storage(): array
    {
        $writable = is_dir($this->storagePath) && is_writable($this->storagePath);
        $free = @disk_free_space($this->storagePath);
        $total = @disk_total_space($this->storagePath);
        $usedPct = ($total && $free !== false) ? (int) round((($total - $free) / $total) * 100) : null;
        $status = ! $writable ? 'down' : (($usedPct !== null && $usedPct >= 90) ? 'warn' : 'ok');

        return $this->panel('storage', 'Storage', $status, [
            ['k' => 'Path', 'v' => $this->shortPath($this->storagePath)],
            ['k' => 'Writable', 'v' => $writable ? 'yes' : 'no'],
            ['k' => 'Disk free', 'v' => $free !== false ? $this->humanBytes((int) $free) : 'n/a'],
            ['k' => 'Disk total', 'v' => $total !== false ? $this->humanBytes((int) $total) : 'n/a'],
            ['k' => 'Used', 'v' => $usedPct !== null ? $usedPct . '%' : 'n/a'],
            ['k' => 'Uploads', 'v' => $this->humanBytes($this->dirSize($this->storagePath . '/files'))],
        ]);
    }

    /** @return array{key: string, label: string, status: string, items: list<array{k: string, v: string}>} */
    private function cache(): array
    {
        $cacheDir = $this->storagePath . '/cache';
        $compiledDir = $this->storagePath . '/compiled';
        $writable = is_dir($cacheDir) && is_writable($cacheDir);

        return $this->panel('cache', 'Cache', $writable ? 'ok' : 'warn', [
            ['k' => 'Driver', 'v' => 'filesystem'],
            ['k' => 'Cache dir', 'v' => $writable ? 'writable' : 'not writable'],
            ['k' => 'Cache size', 'v' => $this->humanBytes($this->dirSize($cacheDir))],
            ['k' => 'Compiled views', 'v' => $this->humanBytes($this->dirSize($compiledDir))],
            ['k' => 'OPcache', 'v' => $this->opcacheEnabled() ? 'enabled' : 'disabled'],
        ]);
    }

    /** @return array{key: string, label: string, status: string, items: list<array{k: string, v: string}>} */
    private function queue(): array
    {
        // The automation queue is the workflow execution log.
        $total = $this->int('SELECT COUNT(*) AS c FROM workflow_executions');
        $failed = $this->int("SELECT COUNT(*) AS c FROM workflow_executions WHERE status = 'failed'");
        $running = $this->int("SELECT COUNT(*) AS c FROM workflow_executions WHERE status IN ('running', 'pending')");
        $status = $failed > 0 ? 'warn' : 'ok';

        return $this->panel('queue', 'Automation queue', $status, [
            ['k' => 'Backend', 'v' => 'workflow executions (synchronous)'],
            ['k' => 'Executions', 'v' => (string) $total],
            ['k' => 'In flight', 'v' => (string) $running],
            ['k' => 'Failed', 'v' => (string) $failed],
        ]);
    }

    /** @return array{key: string, label: string, status: string, items: list<array{k: string, v: string}>} */
    private function mail(): array
    {
        // Mail is configured per workspace; report how many have an SMTP host set.
        $configured = $this->int(
            "SELECT COUNT(*) AS c FROM workspace_settings WHERE `key` = 'mail.smtp_host' AND `value` IS NOT NULL AND `value` NOT IN ('\"\"', 'null', '')",
        );
        $phpMail = function_exists('mail');
        $status = $configured > 0 ? 'ok' : 'info';

        return $this->panel('mail', 'Mail', $status, [
            ['k' => 'Model', 'v' => 'per-workspace SMTP'],
            ['k' => 'Workspaces configured', 'v' => (string) $configured],
            ['k' => 'PHP mail()', 'v' => $phpMail ? 'available' : 'unavailable'],
            ['k' => 'OpenSSL', 'v' => extension_loaded('openssl') ? 'loaded (TLS/SSL ready)' : 'missing'],
        ]);
    }

    /**
     * @param  array{https?: bool, forwarded_proto?: string, host?: string}  $request
     * @return array{key: string, label: string, status: string, items: list<array{k: string, v: string}>}
     */
    private function transport(array $request): array
    {
        $https = (bool) ($request['https'] ?? false);
        $proto = (string) ($request['forwarded_proto'] ?? '');
        $secure = $https || strtolower($proto) === 'https';
        $status = $secure ? 'ok' : 'warn';

        return $this->panel('ssl', 'SSL / Transport', $status, [
            ['k' => 'Scheme', 'v' => $secure ? 'https' : 'http'],
            ['k' => 'Host', 'v' => (string) ($request['host'] ?? 'n/a')],
            ['k' => 'Behind proxy', 'v' => $proto !== '' ? 'yes (X-Forwarded-Proto: ' . $proto . ')' : 'no'],
            ['k' => 'OpenSSL version', 'v' => defined('OPENSSL_VERSION_TEXT') ? (string) OPENSSL_VERSION_TEXT : 'n/a'],
        ]);
    }

    /** @return array{key: string, label: string, status: string, items: list<array{k: string, v: string}>} */
    private function scheduler(): array
    {
        $lastBackup = $this->scalar("SELECT started_at FROM backups ORDER BY started_at DESC LIMIT 1");
        $lastError = $this->scalar('SELECT occurred_at FROM error_events ORDER BY occurred_at DESC LIMIT 1');

        return $this->panel('workers', 'Scheduler & workers', 'info', [
            ['k' => 'Model', 'v' => 'on-demand (monitors evaluate on view)'],
            ['k' => 'Background workers', 'v' => 'none required'],
            ['k' => 'Last backup', 'v' => $lastBackup !== null ? $lastBackup . ' UTC' : 'never'],
            ['k' => 'Last error event', 'v' => $lastError !== null ? $lastError . ' UTC' : 'none'],
        ]);
    }

    /** @return array{key: string, label: string, status: string, items: list<array{k: string, v: string}>} */
    private function runtime(): array
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : null;
        $loadStr = is_array($load) ? implode(' / ', array_map(static fn ($l): string => number_format((float) $l, 2), $load)) : 'n/a';

        return $this->panel('runtime', 'Runtime & performance', 'ok', [
            ['k' => 'PHP', 'v' => PHP_VERSION],
            ['k' => 'Memory limit', 'v' => (string) ini_get('memory_limit')],
            ['k' => 'Memory in use', 'v' => $this->humanBytes(memory_get_usage(true))],
            ['k' => 'Peak memory', 'v' => $this->humanBytes(memory_get_peak_usage(true))],
            ['k' => 'Load avg (1/5/15m)', 'v' => $loadStr],
            ['k' => 'Extensions', 'v' => $this->extensionsSummary()],
        ]);
    }

    private function extensionsSummary(): string
    {
        $required = ['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'json'];
        $parts = [];
        foreach ($required as $ext) {
            $parts[] = $ext . (extension_loaded($ext) ? '✓' : '✗');
        }

        return implode(' ', $parts);
    }

    private function opcacheEnabled(): bool
    {
        if (! function_exists('opcache_get_status')) {
            return false;
        }
        try {
            $status = @opcache_get_status(false);

            return is_array($status) && ($status['opcache_enabled'] ?? false);
        } catch (Throwable) {
            return false;
        }
    }

    private function dirSize(string $dir): int
    {
        if (! is_dir($dir)) {
            return 0;
        }
        $bytes = 0;
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile()) {
                    $bytes += (int) $file->getSize();
                }
            }
        } catch (Throwable) {
            return $bytes;
        }

        return $bytes;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return number_format($bytes / (1024 ** $i), $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }

    private function shortPath(string $path): string
    {
        return str_starts_with($path, $this->basePath) ? '…' . substr($path, strlen($this->basePath)) : $path;
    }

    /** @param array<int, mixed> $bindings */
    private function int(string $sql, array $bindings = []): int
    {
        try {
            return (int) ($this->connection->selectOne($sql, $bindings)['c'] ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private function scalar(string $sql): ?string
    {
        try {
            $row = $this->connection->selectOne($sql);
            if ($row === null) {
                return null;
            }
            $value = reset($row);

            return $value !== false && $value !== null ? (string) $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<array{k: string, v: string}>  $items
     * @return array{key: string, label: string, status: string, items: list<array{k: string, v: string}>}
     */
    private function panel(string $key, string $label, string $status, array $items): array
    {
        return ['key' => $key, 'label' => $label, 'status' => $status, 'items' => $items];
    }
}
