<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Installer\Application;

use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Modules\Installer\Application\Exceptions\InstallerException;
use HaHireAI\Modules\Permissions\Application\PermissionSeeder;
use HaHireAI\Modules\Users\Application\UserRegistrar;
use Throwable;

/**
 * Orchestrates the zero-touch installation steps (docs/INSTALLER_ARCHITECTURE.md,
 * docs/INSTALLATION_FLOW.md). The browser wizard calls these step methods and
 * streams the log; the same logic is reusable from the CLI.
 */
final class Installer
{
    public function __construct(
        private readonly MigrationRunner $migrations,
        private readonly PermissionSeeder $permissions,
        private readonly UserRegistrar $users,
        private readonly EventDispatcher $events,
        private readonly string $lockPath,
        private readonly string $migrationsDir,
        private readonly string $envPath = '',
    ) {
    }

    /**
     * Test that the supplied database credentials connect, before we write them.
     *
     * @param  array{host:string,port:int,database:string,username:string,password:string}  $db
     */
    public function testDatabase(array $db): void
    {
        try {
            (new Connection($db + ['charset' => 'utf8mb4']))->select('SELECT 1');
        } catch (Throwable $e) {
            throw new InstallerException('Could not connect to the database: ' . $e->getMessage());
        }
    }

    /**
     * Persist the database credentials to the .env file so the app (and the next
     * request) connect with them — the buyer never edits a file by hand.
     *
     * @param  array{host:string,port:int,database:string,username:string,password:string}  $db
     */
    public function writeDatabaseConfig(array $db): void
    {
        if ($this->envPath === '') {
            throw new InstallerException('No .env path configured.');
        }

        $values = [
            'DB_HOST' => (string) $db['host'],
            'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => (string) $db['database'],
            'DB_USERNAME' => (string) $db['username'],
            'DB_PASSWORD' => (string) $db['password'],
        ];

        $existing = is_file($this->envPath) ? (string) file_get_contents($this->envPath) : '';
        $lines = $existing === '' ? [] : (preg_split('/\r?\n/', $existing) ?: []);
        $seen = [];
        foreach ($lines as &$line) {
            if (preg_match('/^([A-Z0-9_]+)=/', $line, $m) && isset($values[$m[1]])) {
                $line = $m[1] . '=' . $this->envQuote($values[$m[1]]);
                $seen[$m[1]] = true;
            }
        }
        unset($line);
        foreach ($values as $key => $value) {
            if (! isset($seen[$key])) {
                $lines[] = $key . '=' . $this->envQuote($value);
            }
        }

        if (@file_put_contents($this->envPath, implode("\n", array_filter($lines, static fn ($l) => $l !== null)) . "\n") === false) {
            throw new InstallerException('Could not write .env — make the project root writable for setup.');
        }
    }

    /**
     * The fixed allow-list of setup/maintenance operations the install console may
     * run. There is NO arbitrary command execution — only these named, in-process
     * operations — so the console can never become a remote shell.
     *
     * @return array<string,string> key => human label
     */
    public function consoleCommands(): array
    {
        return [
            'migrate' => 'Run database migrations',
            'permissions:sync' => 'Sync the permission catalog',
            'cache:clear' => 'Clear compiled/cache files',
            'requirements' => 'Re-check server requirements',
        ];
    }

    /**
     * Run one allow-listed console operation in-process and return its output.
     * Rejects anything not on the allow-list (no shell, ever).
     */
    public function runConsole(string $command): string
    {
        if (! array_key_exists($command, $this->consoleCommands())) {
            throw new InstallerException('Command not allowed.');
        }

        return match ($command) {
            'migrate' => $this->describeMigrations($this->migrations->run($this->migrationsDir)),
            'permissions:sync' => 'Synced ' . $this->permissions->seed() . ' permission(s).',
            'cache:clear' => $this->clearCache(),
            'requirements' => $this->describeRequirements(),
            default => 'Nothing to do.',
        };
    }

    public function isInstalled(): bool
    {
        return is_file($this->lockPath);
    }

    /**
     * Server/runtime requirement checks for the Welcome step.
     *
     * @return list<array{name: string, ok: bool, detail: string}>
     */
    public function requirements(): array
    {
        $checks = [
            ['name' => 'PHP >= 8.3', 'ok' => version_compare(PHP_VERSION, '8.3.0', '>='), 'detail' => PHP_VERSION],
        ];

        foreach (['pdo_mysql', 'mbstring', 'openssl', 'json', 'ctype', 'fileinfo'] as $ext) {
            $checks[] = ['name' => "ext-{$ext}", 'ok' => extension_loaded($ext), 'detail' => extension_loaded($ext) ? 'loaded' : 'missing'];
        }

        $storage = dirname($this->lockPath);
        $checks[] = ['name' => 'storage writable', 'ok' => is_dir($storage) && is_writable($storage), 'detail' => $storage];

        return $checks;
    }

    public function requirementsSatisfied(): bool
    {
        foreach ($this->requirements() as $check) {
            if (! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Run the full installation. Idempotent guard: refuses if already installed.
     *
     * @param  array{name: string, email: string, password: string}  $systemOwner
     * @param  callable(string):void|null  $log
     * @return array{migrations: list<string>, permissions: int, system_owner_id: string}
     */
    public function install(array $systemOwner, ?callable $log = null): array
    {
        if ($this->isInstalled()) {
            throw new InstallerException('Already installed; setup is locked.');
        }

        $log && $log('Running migrations...');
        $applied = $this->migrations->run($this->migrationsDir, $log);

        $log && $log('Seeding permission catalog...');
        $seeded = $this->permissions->seed();

        $log && $log('Creating the first System Owner...');
        $ownerId = $this->users->createSystemOwner($systemOwner['name'], $systemOwner['email'], $systemOwner['password']);

        $log && $log('Finalizing & locking setup...');
        $this->lock();

        // Reactor modules (e.g. Billing) seed their own data on install.
        $this->events->dispatch('platform.installed', ['system_owner_id' => $ownerId]);

        $log && $log('Completed.');

        return ['migrations' => $applied, 'permissions' => $seeded, 'system_owner_id' => $ownerId];
    }

    public function lock(): void
    {
        $dir = dirname($this->lockPath);

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        file_put_contents($this->lockPath, gmdate('c') . PHP_EOL);
    }

    public function unlock(): void
    {
        if (is_file($this->lockPath)) {
            @unlink($this->lockPath);
        }
    }

    private function envQuote(string $value): string
    {
        return preg_match('/\s|"|#/', $value) ? '"' . str_replace('"', '\"', $value) . '"' : $value;
    }

    /** @param list<string> $applied */
    private function describeMigrations(array $applied): string
    {
        if ($applied === []) {
            return 'Nothing to migrate — schema is up to date.';
        }

        return 'Applied ' . count($applied) . " migration(s):\n  " . implode("\n  ", $applied);
    }

    private function clearCache(): string
    {
        $cleared = 0;
        foreach (['cache', 'compiled'] as $sub) {
            $dir = dirname($this->lockPath) . '/' . $sub;
            if (! is_dir($dir)) {
                continue;
            }
            foreach (glob($dir . '/*') ?: [] as $file) {
                if (is_file($file) && @unlink($file)) {
                    $cleared++;
                }
            }
        }

        return "Cleared {$cleared} cached file(s).";
    }

    private function describeRequirements(): string
    {
        $lines = [];
        foreach ($this->requirements() as $req) {
            $lines[] = ($req['ok'] ? '[ok] ' : '[!!] ') . $req['name'] . ' — ' . $req['detail'];
        }

        return implode("\n", $lines);
    }
}
