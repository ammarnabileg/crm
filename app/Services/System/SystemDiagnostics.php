<?php

declare(strict_types=1);

namespace App\Services\System;

use PDO;
use Throwable;

/**
 * Read-only system health report. Runs a battery of environment, database,
 * storage, cache, mail, logging, security and AI-layer checks and returns them
 * grouped for display. Nothing here mutates state beyond a couple of safe,
 * self-cleaning write probes (a temporary table, a throwaway cache key, a temp
 * file), and EVERY check is wrapped so a failure becomes a 'fail' status rather
 * than a thrown exception — the report must always render.
 *
 * Part of the Setup & Installer Bible: an admin can confirm the box is healthy
 * from the browser at any time, with zero CLI/SSH.
 */
final class SystemDiagnostics
{
    /** Warn when free disk space drops below this many bytes (1 GiB). */
    private const DISK_WARN_BYTES = 1073741824;

    /**
     * Run every group of checks and return them grouped.
     *
     * @return array<string, array<int, array{name:string,status:string,value:string,hint?:string}>>
     */
    public function run(): array
    {
        return [
            'PHP'                   => $this->phpChecks(),
            'Extensions'            => $this->extensionChecks(),
            'Database'              => $this->databaseChecks(),
            'Storage'               => $this->storageChecks(),
            'Cache'                 => $this->cacheChecks(),
            'Queue'                 => $this->queueChecks(),
            'Mail'                  => $this->mailChecks(),
            'Scheduler'             => $this->schedulerChecks(),
            'Logs'                  => $this->logChecks(),
            'Environment & Security' => $this->securityChecks(),
            'AI Layer'              => $this->aiChecks(),
        ];
    }

    /**
     * Flatten the grouped report into pass/warn/fail tallies for the summary header.
     *
     * @param array<string, array<int, array<string, mixed>>> $groups
     * @return array{pass:int,warn:int,fail:int,total:int}
     */
    public function summarize(array $groups): array
    {
        $summary = ['pass' => 0, 'warn' => 0, 'fail' => 0, 'total' => 0];

        foreach ($groups as $checks) {
            foreach ($checks as $check) {
                $status = $check['status'] ?? 'fail';
                if (! isset($summary[$status])) {
                    $status = 'fail';
                }
                $summary[$status]++;
                $summary['total']++;
            }
        }

        return $summary;
    }

    // --- PHP ----------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function phpChecks(): array
    {
        return [
            $this->guard('PHP version', function (): array {
                $ok = version_compare(PHP_VERSION, '8.2.0', '>=');

                return [
                    'status' => $ok ? 'pass' : 'fail',
                    'value'  => PHP_VERSION,
                    'hint'   => $ok ? null : 'HalaOps requires PHP 8.2 or newer. Ask your host to upgrade the PHP version for this site.',
                ];
            }),
            $this->guard('memory_limit', function (): array {
                $raw = (string) ini_get('memory_limit');
                $bytes = $this->bytesFromIni($raw);
                // -1 means unlimited; otherwise warn under 128M.
                $ok = $bytes === -1 || $bytes >= 128 * 1024 * 1024;

                return [
                    'status' => $ok ? 'pass' : 'warn',
                    'value'  => $raw,
                    'hint'   => $ok ? null : 'A memory_limit of at least 128M is recommended for imports and AI calls.',
                ];
            }),
            $this->guard('max_execution_time', function (): array {
                $raw = (string) ini_get('max_execution_time');
                $val = (int) $raw;
                // 0 = unlimited (typical under CLI); warn if a low cap is set.
                $ok = $val === 0 || $val >= 30;

                return [
                    'status' => $ok ? 'pass' : 'warn',
                    'value'  => $val === 0 ? '0 (unlimited)' : $raw . 's',
                    'hint'   => $ok ? null : 'Long-running tasks (migrations, bulk jobs) may time out. 30s or more is recommended.',
                ];
            }),
            $this->guard('upload_max_filesize', function (): array {
                $raw = (string) ini_get('upload_max_filesize');

                return ['status' => 'pass', 'value' => $raw !== '' ? $raw : 'n/a'];
            }),
            $this->guard('post_max_size', function (): array {
                $raw = (string) ini_get('post_max_size');
                $post = $this->bytesFromIni($raw);
                $upload = $this->bytesFromIni((string) ini_get('upload_max_filesize'));
                // post_max_size should be >= upload_max_filesize or uploads silently truncate.
                $ok = $post === -1 || $post === 0 || $upload <= 0 || $post >= $upload;

                return [
                    'status' => $ok ? 'pass' : 'warn',
                    'value'  => $raw !== '' ? $raw : 'n/a',
                    'hint'   => $ok ? null : 'post_max_size is smaller than upload_max_filesize; large uploads will fail. Raise post_max_size.',
                ];
            }),
        ];
    }

    // --- Extensions ---------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function extensionChecks(): array
    {
        $checks = [];

        // Required extensions: missing => fail.
        foreach (['pdo_mysql', 'mbstring', 'openssl', 'json', 'fileinfo', 'curl', 'gd', 'intl', 'zip'] as $ext) {
            $checks[] = $this->guard("ext: {$ext}", function () use ($ext): array {
                $loaded = extension_loaded($ext);

                return [
                    'status' => $loaded ? 'pass' : 'fail',
                    'value'  => $loaded ? 'loaded' : 'missing',
                    'hint'   => $loaded ? null : "Enable the PHP {$ext} extension in your hosting control panel or php.ini.",
                ];
            });
        }

        // Optional extensions: missing => warn (not fail).
        foreach (['imagick', 'redis'] as $ext) {
            $checks[] = $this->guard("ext: {$ext} (optional)", function () use ($ext): array {
                $loaded = extension_loaded($ext);

                return [
                    'status' => $loaded ? 'pass' : 'warn',
                    'value'  => $loaded ? 'loaded' : 'not installed',
                    'hint'   => $loaded ? null : "Optional. {$ext} improves " . ($ext === 'redis' ? 'cache/queue performance' : 'image processing') . ' but is not required.',
                ];
            });
        }

        return $checks;
    }

    // --- Database -----------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function databaseChecks(): array
    {
        return [
            $this->guard('Connectivity', function (): array {
                $one = app('db')->scalar('SELECT 1');
                $ok = (int) $one === 1;

                return [
                    'status' => $ok ? 'pass' : 'fail',
                    'value'  => $ok ? 'connected' : 'unexpected response',
                    'hint'   => $ok ? null : 'The database answered but not as expected. Check the connection.',
                ];
            }),
            $this->guard('Server version', function (): array {
                $version = (string) app('db')->pdo()->getAttribute(PDO::ATTR_SERVER_VERSION);

                return ['status' => 'pass', 'value' => $version !== '' ? $version : 'unknown'];
            }),
            $this->guard('Table count', function (): array {
                $count = app('db')->scalar(
                    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
                );
                $count = (int) $count;

                return [
                    'status' => $count > 0 ? 'pass' : 'warn',
                    'value'  => $count . ' tables',
                    'hint'   => $count > 0 ? null : 'No tables found in the schema. Has the installer run its migrations?',
                ];
            }),
            $this->guard('Write probe', function (): array {
                $db = app('db');
                // CREATE TEMPORARY TABLE is session-scoped and auto-dropped, so a
                // failed cleanup can never leave residue in the schema.
                $table = 'halaops_diag_' . bin2hex(random_bytes(4));
                $db->unprepared("CREATE TEMPORARY TABLE `{$table}` (id INT)");
                try {
                    $db->statement("INSERT INTO `{$table}` (id) VALUES (1)");
                    $back = (int) $db->scalar("SELECT id FROM `{$table}` LIMIT 1");
                    $ok = $back === 1;
                } finally {
                    // Best-effort drop; temp tables vanish with the connection anyway.
                    try {
                        $db->unprepared("DROP TEMPORARY TABLE IF EXISTS `{$table}`");
                    } catch (Throwable) {
                        // ignore — connection teardown will reclaim it.
                    }
                }

                return [
                    'status' => $ok ? 'pass' : 'fail',
                    'value'  => $ok ? 'read/write OK' : 'write verification failed',
                    'hint'   => $ok ? null : 'The database user can connect but writes did not verify. Check table privileges.',
                ];
            }),
        ];
    }

    // --- Storage ------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function storageChecks(): array
    {
        $checks = [];

        $dirs = [
            'storage'              => storage_path(),
            'storage/logs'         => storage_path('logs'),
            'storage/cache'        => storage_path('cache'),
            'storage/framework'    => storage_path('framework'),
            'storage/app/uploads'  => storage_path('app/uploads'),
        ];

        foreach ($dirs as $label => $path) {
            $checks[] = $this->guard("Writable: {$label}", function () use ($path): array {
                $exists = is_dir($path);
                $writable = $exists && is_writable($path);

                if (! $exists) {
                    return [
                        'status' => 'fail',
                        'value'  => 'missing',
                        'hint'   => 'Directory does not exist: ' . $path . ' — create it and make it writable by the web server.',
                    ];
                }

                return [
                    'status' => $writable ? 'pass' : 'fail',
                    'value'  => $writable ? 'writable' : 'not writable',
                    'hint'   => $writable ? null : 'Make ' . $path . ' writable by the web-server user (e.g. chmod/owner via your host file manager).',
                ];
            });
        }

        $checks[] = $this->guard('Free disk space', function (): array {
            $free = @disk_free_space(storage_path());
            if ($free === false) {
                return ['status' => 'warn', 'value' => 'unknown', 'hint' => 'Could not read free disk space for the storage volume.'];
            }
            $free = (float) $free;
            $ok = $free >= self::DISK_WARN_BYTES;

            return [
                'status' => $ok ? 'pass' : 'warn',
                'value'  => $this->humanBytes($free) . ' free',
                'hint'   => $ok ? null : 'Less than 1 GB of disk space remains. Free up space to avoid failed uploads, logs and backups.',
            ];
        });

        return $checks;
    }

    // --- Cache --------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function cacheChecks(): array
    {
        return [
            $this->guard('Read/write probe', function (): array {
                $cache = app('cache');
                $key = 'halaops:diag:' . bin2hex(random_bytes(6));
                $value = 'ok-' . random_int(1000, 9999);

                $cache->put($key, $value, 60);
                $read = $cache->get($key);
                $cache->forget($key);
                $gone = $cache->get($key, '__missing__');

                $ok = $read === $value && $gone === '__missing__';

                return [
                    'status' => $ok ? 'pass' : 'fail',
                    'value'  => $ok ? 'write/read/forget OK' : 'probe failed',
                    'hint'   => $ok ? null : 'The cache store could not round-trip a value. Check storage/cache writability or the configured cache driver.',
                ];
            }),
        ];
    }

    // --- Queue --------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function queueChecks(): array
    {
        return [
            $this->guard('Queue store', function (): array {
                if (! $this->tableExists('queued_jobs')) {
                    return [
                        'status' => 'warn',
                        'value'  => 'table missing',
                        'hint'   => 'The queued_jobs table is absent; long tasks would run inline. Run migrations to enable async queueing.',
                    ];
                }
                $pending = (int) app('db')->scalar('SELECT COUNT(*) FROM `queued_jobs`');
                // A very large backlog means no worker is draining the queue.
                $ok = $pending < 10000;

                return [
                    'status' => $ok ? 'pass' : 'warn',
                    'value'  => $pending . ' job' . ($pending === 1 ? '' : 's') . ' pending',
                    'hint'   => $ok ? null : 'Large queue backlog — ensure the queue worker (cron-driven) is running to drain jobs.',
                ];
            }),
            $this->guard('Failed jobs', function (): array {
                if (! $this->tableExists('failed_jobs')) {
                    return ['status' => 'pass', 'value' => 'n/a'];
                }
                $failed = (int) app('db')->scalar('SELECT COUNT(*) FROM `failed_jobs`');

                return [
                    'status' => $failed === 0 ? 'pass' : 'warn',
                    'value'  => $failed === 0 ? 'none' : $failed . ' failed',
                    'hint'   => $failed === 0 ? null : 'There are failed background jobs — review failed_jobs and retry or discard them.',
                ];
            }),
        ];
    }

    // --- Scheduler (cron) ---------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function schedulerChecks(): array
    {
        return [
            $this->guard('Scheduled tasks', function (): array {
                if (! $this->tableExists('scheduled_tasks')) {
                    return [
                        'status' => 'warn',
                        'value'  => 'table missing',
                        'hint'   => 'The scheduled_tasks table is absent. Run migrations to enable the scheduler.',
                    ];
                }
                $active = (int) app('db')->scalar('SELECT COUNT(*) FROM `scheduled_tasks` WHERE is_active = 1');

                return ['status' => 'pass', 'value' => $active . ' active task' . ($active === 1 ? '' : 's')];
            }),
            $this->guard('Scheduler heartbeat', function (): array {
                if (! $this->tableExists('scheduled_tasks')) {
                    return ['status' => 'warn', 'value' => 'unknown'];
                }
                $active = (int) app('db')->scalar('SELECT COUNT(*) FROM `scheduled_tasks` WHERE is_active = 1');
                if ($active === 0) {
                    return ['status' => 'pass', 'value' => 'no active tasks'];
                }
                $last = app('db')->scalar('SELECT MAX(last_run_at) FROM `scheduled_tasks` WHERE is_active = 1');
                if ($last === null) {
                    return [
                        'status' => 'warn',
                        'value'  => 'never run',
                        'hint'   => 'Active scheduled tasks have never run. Add a server cron entry that hits the scheduler so background work fires.',
                    ];
                }
                $recent = (time() - strtotime((string) $last)) < 26 * 3600;

                return [
                    'status' => $recent ? 'pass' : 'warn',
                    'value'  => 'last run ' . $last,
                    'hint'   => $recent ? null : 'No scheduled task has run in over a day — the server cron may not be wired to the scheduler.',
                ];
            }),
        ];
    }

    // --- Mail ---------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function mailChecks(): array
    {
        return [
            $this->guard('Mail delivery', function (): array {
                $enabled = (bool) config('mail.enabled', false);

                return [
                    'status' => $enabled ? 'pass' : 'warn',
                    'value'  => $enabled ? 'enabled' : 'disabled (logging to storage/logs)',
                    'hint'   => $enabled ? null : 'Mail is disabled, so messages are written to the log instead of sent. Enable it once a working MTA is available.',
                ];
            }),
            $this->guard('From address', function (): array {
                $from = (string) config('mail.from_address', '');
                $valid = $from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL) !== false;

                return [
                    'status' => $valid ? 'pass' : 'warn',
                    'value'  => $from !== '' ? $from : 'not set',
                    'hint'   => $valid ? null : 'Set a valid MAIL_FROM_ADDRESS so outgoing mail has a sender.',
                ];
            }),
        ];
    }

    // --- Logs ---------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function logChecks(): array
    {
        return [
            $this->guard('Log directory', function (): array {
                $path = storage_path('logs');
                $exists = is_dir($path);
                $writable = $exists && is_writable($path);

                if (! $exists) {
                    return ['status' => 'fail', 'value' => 'missing', 'hint' => 'storage/logs does not exist — create it and make it writable.'];
                }

                return [
                    'status' => $writable ? 'pass' : 'fail',
                    'value'  => $writable ? 'writable' : 'not writable',
                    'hint'   => $writable ? null : 'Make storage/logs writable so the application can record errors.',
                ];
            }),
            $this->guard('Log files', function (): array {
                $path = storage_path('logs');
                $files = is_dir($path) ? glob($path . '/*.log') : [];
                $count = is_array($files) ? count($files) : 0;

                return ['status' => 'pass', 'value' => $count . ' log file' . ($count === 1 ? '' : 's')];
            }),
        ];
    }

    // --- Environment & Security --------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function securityChecks(): array
    {
        return [
            $this->guard('APP_DEBUG', function (): array {
                $debug = (bool) config('app.debug', false);
                $isProd = strtolower((string) config('app.env', 'production')) === 'production';

                if (! $debug) {
                    return ['status' => 'pass', 'value' => 'off'];
                }

                // Debug on is a hard fail in production, otherwise a warning.
                return [
                    'status' => $isProd ? 'fail' : 'warn',
                    'value'  => 'on',
                    'hint'   => 'APP_DEBUG should be false in production — it leaks stack traces and config. Set APP_DEBUG=false.',
                ];
            }),
            $this->guard('APP_ENV', function (): array {
                $env = (string) config('app.env', 'production');

                return ['status' => 'pass', 'value' => $env !== '' ? $env : 'unknown'];
            }),
            $this->guard('APP_KEY', function (): array {
                $key = (string) config('app.key', '');
                $ok = $key !== '';

                return [
                    'status' => $ok ? 'pass' : 'fail',
                    'value'  => $ok ? 'set' : 'missing',
                    'hint'   => $ok ? null : 'APP_KEY is empty — encrypted values (including AI keys) cannot be secured. Generate one in setup.',
                ];
            }),
            $this->guard('Session cookie secure flag', function (): array {
                $secure = (bool) config('session.secure', false);
                $https = $this->requestIsSecure();

                if ($secure) {
                    return ['status' => 'pass', 'value' => 'on'];
                }

                // Only a real problem once the site is served over HTTPS.
                return [
                    'status' => $https ? 'warn' : 'pass',
                    'value'  => $https ? 'off (site is on HTTPS)' : 'off',
                    'hint'   => $https ? 'The site is served over HTTPS but SESSION_SECURE is off; set it to true so the session cookie is HTTPS-only.' : null,
                ];
            }),
            $this->guard('HTTPS', function (): array {
                $https = $this->requestIsSecure();

                return [
                    'status' => $https ? 'pass' : 'warn',
                    'value'  => $https ? 'secure (https)' : 'not secure (http)',
                    'hint'   => $https ? null : 'Serve the app over HTTPS in production to protect logins and data in transit.',
                ];
            }),
        ];
    }

    // --- AI Layer -----------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function aiChecks(): array
    {
        return [
            $this->guard('AI providers', function (): array {
                if (! $this->tableExists('ai_providers')) {
                    return [
                        'status' => 'warn',
                        'value'  => 'table missing',
                        'hint'   => 'The ai_providers table is absent. Run migrations to enable the AI layer.',
                    ];
                }
                $count = (int) app('db')->scalar('SELECT COUNT(*) FROM `ai_providers`');

                return [
                    'status' => $count > 0 ? 'pass' : 'warn',
                    'value'  => $count . ' provider' . ($count === 1 ? '' : 's') . ' registered',
                    'hint'   => $count > 0 ? null : 'No AI providers seeded yet. Seed the provider catalogue to let tenants configure AI.',
                ];
            }),
            $this->guard('Tenant AI keys table', function (): array {
                // The keys table is named ai_credentials in this build; accept the
                // Bible's tenant_ai_keys name too so the check is forward-compatible.
                $table = $this->firstExistingTable(['tenant_ai_keys', 'ai_credentials']);
                if ($table === null) {
                    return [
                        'status' => 'warn',
                        'value'  => 'not found',
                        'hint'   => 'No per-tenant AI keys table (tenant_ai_keys / ai_credentials). Run migrations if AI features are needed.',
                    ];
                }
                $count = (int) app('db')->scalar("SELECT COUNT(*) FROM `{$table}`");

                return [
                    'status' => 'pass',
                    'value'  => $table . ' present (' . $count . ' key' . ($count === 1 ? '' : 's') . ')',
                ];
            }),
        ];
    }

    // --- Helpers ------------------------------------------------------------

    /**
     * Run a single check body, normalising its result and turning any thrown
     * error into a 'fail' carrying the exception message. A check never throws.
     *
     * @param callable():array<string,mixed> $body
     * @return array{name:string,status:string,value:string,hint?:string}
     */
    private function guard(string $name, callable $body): array
    {
        try {
            $result = $body();
            $status = $result['status'] ?? 'fail';
            if (! in_array($status, ['pass', 'warn', 'fail'], true)) {
                $status = 'fail';
            }

            $check = [
                'name'   => $name,
                'status' => $status,
                'value'  => (string) ($result['value'] ?? ''),
            ];

            if (isset($result['hint']) && $result['hint'] !== null && $result['hint'] !== '') {
                $check['hint'] = (string) $result['hint'];
            }

            return $check;
        } catch (Throwable $e) {
            return [
                'name'   => $name,
                'status' => 'fail',
                'value'  => $e->getMessage(),
                'hint'   => 'This check could not complete; the error above is the reason.',
            ];
        }
    }

    /** Whether the current request is being served over HTTPS (defensive). */
    private function requestIsSecure(): bool
    {
        try {
            return request()->isSecure();
        } catch (Throwable) {
            return false;
        }
    }

    /** Whether a table exists in the current schema. */
    private function tableExists(string $table): bool
    {
        $found = app('db')->scalar(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );

        return (int) $found > 0;
    }

    /**
     * Return the first table from the list that exists, or null.
     *
     * @param string[] $tables
     */
    private function firstExistingTable(array $tables): ?string
    {
        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                return $table;
            }
        }

        return null;
    }

    /**
     * Convert a php.ini shorthand size (e.g. "128M", "1G", "512K") to bytes.
     * Returns -1 for unlimited (the literal "-1").
     */
    private function bytesFromIni(string $value): int
    {
        $value = trim($value);
        if ($value === '' ) {
            return 0;
        }
        if ($value === '-1') {
            return -1;
        }

        $unit = strtolower($value[strlen($value) - 1]);
        $number = (int) $value;

        return match ($unit) {
            'g'     => $number * 1024 * 1024 * 1024,
            'm'     => $number * 1024 * 1024,
            'k'     => $number * 1024,
            default => (int) $value,
        };
    }

    /** Human-readable byte size. */
    private function humanBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $bytes : number_format($bytes, 1)) . ' ' . $units[$i];
    }
}
