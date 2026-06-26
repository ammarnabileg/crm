<?php

declare(strict_types=1);

namespace App\Services\Install;

use App\Core\Database;
use App\Core\Encrypter;
use App\Core\Hash;
use App\Services\Rbac\RbacManager;
use Database\Migrator;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Drives the no-CLI web installer.
 *
 * The whole point is that a non-technical buyer can stand the platform up by
 * uploading files and clicking through a wizard — no SSH, Composer, Artisan or
 * npm. Every step is resumable: progress and the (storage-only, never
 * web-served) configuration are persisted to a JSON state file so a failure
 * continues from the last good step instead of restarting.
 */
final class InstallManager
{
    public const STEPS = ['requirements', 'database', 'migrate', 'seed', 'admin', 'finalize'];

    private string $statePath;
    private string $lockPath;
    private string $envPath;

    public function __construct(private readonly string $basePath)
    {
        $this->statePath = $basePath . '/storage/framework/install_state.json';
        $this->lockPath = $basePath . '/storage/framework/installed';
        $this->envPath = $basePath . '/.env';
    }

    public function isInstalled(): bool
    {
        return is_file($this->lockPath) && is_file($this->envPath);
    }

    // --- State -------------------------------------------------------------

    public function state(): array
    {
        if (! is_file($this->statePath)) {
            return ['completed' => [], 'config' => []];
        }
        $data = json_decode((string) @file_get_contents($this->statePath), true);

        return is_array($data) ? $data + ['completed' => [], 'config' => []] : ['completed' => [], 'config' => []];
    }

    private function saveState(array $state): void
    {
        $dir = dirname($this->statePath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($this->statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    public function markComplete(string $step, array $config = []): void
    {
        $state = $this->state();
        if (! in_array($step, $state['completed'], true)) {
            $state['completed'][] = $step;
        }
        $state['config'] = array_merge($state['config'], $config);
        $this->saveState($state);
    }

    public function isStepComplete(string $step): bool
    {
        return in_array($step, $this->state()['completed'], true);
    }

    public function nextStep(): string
    {
        foreach (self::STEPS as $step) {
            if (! $this->isStepComplete($step)) {
                return $step;
            }
        }

        return 'finalize';
    }

    // --- Step 1: requirements ---------------------------------------------

    /**
     * @return array{checks:array<int,array{name:string,ok:bool,value:string,required:bool}>,passed:bool}
     */
    public function checkRequirements(): array
    {
        $checks = [];

        $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
        $checks[] = ['name' => 'PHP >= 8.2', 'ok' => $phpOk, 'value' => PHP_VERSION, 'required' => true];

        foreach (['pdo_mysql', 'mbstring', 'openssl', 'json', 'fileinfo', 'curl'] as $ext) {
            $checks[] = [
                'name'     => "Extension: {$ext}",
                'ok'       => extension_loaded($ext),
                'value'    => extension_loaded($ext) ? 'loaded' : 'missing',
                'required' => true,
            ];
        }

        foreach (['gd', 'intl', 'zip'] as $ext) {
            $checks[] = [
                'name'     => "Extension: {$ext} (recommended)",
                'ok'       => extension_loaded($ext),
                'value'    => extension_loaded($ext) ? 'loaded' : 'missing',
                'required' => false,
            ];
        }

        foreach (['storage', 'storage/logs', 'storage/cache', 'storage/sessions', 'storage/framework'] as $dir) {
            $path = $this->basePath . '/' . $dir;
            $writable = $this->ensureWritable($path);
            $checks[] = [
                'name'     => "Writable: /{$dir}",
                'ok'       => $writable,
                'value'    => $writable ? 'writable' : 'not writable',
                'required' => true,
            ];
        }

        $rootWritable = is_writable($this->basePath);
        $checks[] = [
            'name'     => 'Writable: project root (.env)',
            'ok'       => $rootWritable || is_file($this->envPath),
            'value'    => $rootWritable ? 'writable' : 'not writable',
            'required' => true,
        ];

        $passed = true;
        foreach ($checks as $check) {
            if ($check['required'] && ! $check['ok']) {
                $passed = false;
            }
        }

        return ['checks' => $checks, 'passed' => $passed];
    }

    private function ensureWritable(string $path): bool
    {
        if (! is_dir($path)) {
            @mkdir($path, 0775, true);
        }

        return is_dir($path) && is_writable($path);
    }

    // --- Step 2: database --------------------------------------------------

    /**
     * Validate connection details and create the database if needed.
     *
     * @param array{host:string,port:string,database:string,username:string,password:string} $creds
     */
    public function configureDatabase(array $creds): void
    {
        $host = trim($creds['host'] ?? '127.0.0.1');
        $port = trim($creds['port'] ?? '3306');
        $database = trim($creds['database'] ?? '');
        $username = trim($creds['username'] ?? '');
        $password = (string) ($creds['password'] ?? '');

        if ($database === '') {
            throw new RuntimeException('Database name is required.');
        }
        if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new RuntimeException('Database name may only contain letters, numbers and underscores.');
        }

        // Connect to the server (no DB selected) to test creds + create the DB.
        try {
            $pdo = new PDO(
                "mysql:host={$host};port={$port};charset=utf8mb4",
                $username,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
        } catch (Throwable $e) {
            throw new RuntimeException('Could not connect to the database server: ' . $e->getMessage());
        }

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$database}`");

        $this->markComplete('database', [
            'db' => compact('host', 'port', 'database', 'username', 'password'),
        ]);
    }

    /**
     * Build a Database connection from the persisted install configuration.
     */
    public function makeDatabase(): Database
    {
        $config = $this->state()['config']['db'] ?? null;
        if (! is_array($config)) {
            throw new RuntimeException('Database has not been configured yet.');
        }

        return new Database([
            'host'      => $config['host'],
            'port'      => $config['port'],
            'database'  => $config['database'],
            'username'  => $config['username'],
            'password'  => $config['password'],
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
    }

    // --- Step 3: migrate ---------------------------------------------------

    /**
     * @param callable(string,bool,?string):void|null $report
     * @return array{ran:string[],failed:?string}
     */
    public function runMigrations(?callable $report = null): array
    {
        $migrator = new Migrator($this->makeDatabase(), $this->basePath . '/database/migrations');
        $result = $migrator->run($report);

        if ($result['failed'] === null) {
            $this->markComplete('migrate');
        }

        return $result;
    }

    // --- Step 4: seed ------------------------------------------------------

    public function runSeeders(): void
    {
        $seeder = require $this->basePath . '/database/seeders/DatabaseSeeder.php';
        $seeder->run($this->makeDatabase());
        $this->markComplete('seed');
    }

    // --- Step 5: admin -----------------------------------------------------

    /**
     * Create (or reuse) the first super-admin user.
     *
     * @param array{name:string,email:string,password:string} $data
     */
    public function createAdmin(array $data): int
    {
        $db = $this->makeDatabase();
        $name = trim($data['name'] ?? '');
        $email = mb_strtolower(trim($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            throw new RuntimeException('Admin name, email and password are required.');
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please provide a valid admin email address.');
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('Admin password must be at least 8 characters.');
        }

        $now = now();
        $userStatusId = $db->scalar(
            'SELECT lv.id FROM lookup_values lv JOIN lookup_categories lc ON lc.id = lv.category_id
             WHERE lc.`key` = ? AND lc.workspace_id IS NULL AND lv.`key` = ? AND lv.workspace_id IS NULL LIMIT 1',
            ['user_status', 'active']
        );
        $userStatusId = $userStatusId !== null ? (int) $userStatusId : null;
        $existing = $db->table('users')->where('email', '=', $email)->first();

        if ($existing !== null) {
            $userId = (int) $existing['id'];
            $db->table('users')->where('id', '=', $userId)->update([
                'name'           => $name,
                'password'       => Hash::make($password),
                'user_status_id' => $userStatusId,
                'updated_at'     => $now,
            ]);
        } else {
            $userId = $db->table('users')->insertGetId([
                'name'              => $name,
                'email'             => $email,
                'password'          => Hash::make($password),
                'locale'            => 'en',
                'user_status_id'    => $userStatusId,
                'email_verified_at' => $now,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        }

        $rbac = new RbacManager($db);
        $superRoleId = $rbac->ensureSuperAdminRole();
        $rbac->assignGlobalRole($userId, $superRoleId);

        $this->markComplete('admin', ['admin_user_id' => $userId, 'admin_email' => $email]);

        return $userId;
    }

    // --- Step 6: finalize --------------------------------------------------

    /**
     * Write the .env file, place the install lock, and remove the sensitive
     * install state.
     *
     * @param array{app_name?:string,app_url?:string} $appData
     */
    public function finalize(array $appData): void
    {
        // Never lock the install in a half-configured state.
        foreach (['database', 'migrate', 'seed', 'admin'] as $required) {
            if (! $this->isStepComplete($required)) {
                throw new RuntimeException("Cannot finalize: the '{$required}' step has not completed yet.");
            }
        }

        $state = $this->state();
        $db = $state['config']['db'] ?? [];

        $env = [
            'APP_NAME'            => $appData['app_name'] ?? 'HalaOps',
            'APP_ENV'             => 'production',
            'APP_DEBUG'           => 'false',
            'APP_KEY'             => Encrypter::generateKey(),
            'APP_URL'             => rtrim($appData['app_url'] ?? '', '/'),
            'APP_LOCALE'          => 'en',
            'APP_FALLBACK_LOCALE' => 'en',
            'APP_TIMEZONE'        => 'Asia/Riyadh',
            'APP_CURRENCY'        => 'SAR',
            '__DB__'              => '',
            'DB_CONNECTION'       => 'mysql',
            'DB_HOST'             => $db['host'] ?? '127.0.0.1',
            'DB_PORT'             => $db['port'] ?? '3306',
            'DB_DATABASE'         => $db['database'] ?? '',
            'DB_USERNAME'         => $db['username'] ?? '',
            'DB_PASSWORD'         => $db['password'] ?? '',
            '__SESSION__'         => '',
            'SESSION_SECURE'      => (str_starts_with($appData['app_url'] ?? '', 'https')) ? 'true' : 'false',
        ];

        $this->writeEnv($env);

        // Place the lock and remove sensitive install state.
        @file_put_contents($this->lockPath, date('c') . PHP_EOL . 'HalaOps installed.' . PHP_EOL);
        $this->markComplete('finalize');
        @unlink($this->statePath);
    }

    private function writeEnv(array $values): void
    {
        $lines = [];
        foreach ($values as $key => $value) {
            if (str_starts_with($key, '__')) {
                $lines[] = '';
                continue;
            }
            $lines[] = $key . '=' . $this->encodeEnvValue((string) $value);
        }

        if (@file_put_contents($this->envPath, implode("\n", $lines) . "\n") === false) {
            throw new RuntimeException('Unable to write the .env file. Make sure the project root is writable.');
        }
    }

    private function encodeEnvValue(string $value): string
    {
        if ($value === '' ) {
            return '';
        }
        // Quote when the value contains characters that would break parsing.
        if (preg_match('/\s|#|"|\'/', $value)) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }

        return $value;
    }
}
