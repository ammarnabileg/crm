<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Installer\Application;

use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Modules\Installer\Application\Exceptions\InstallerException;
use HaHireAI\Modules\Permissions\Application\PermissionSeeder;
use HaHireAI\Modules\Users\Application\UserRegistrar;

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
        private readonly string $lockPath,
        private readonly string $migrationsDir,
    ) {
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
}
