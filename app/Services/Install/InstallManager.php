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
 * Drives the no-CLI web installer (the Setup & Installer Bible).
 *
 * A non-technical buyer stands the platform up by uploading files and clicking
 * through the wizard — no SSH, Composer, Artisan, npm, cron or file editing. Every
 * operation is resumable: progress and the (storage-only, never web-served)
 * configuration live in a JSON state file, so a failure resumes from the last good
 * step. Every requirement failure carries a plain-language solution, never a stack
 * trace. The wizard ends only after a health check and a real final validation.
 */
final class InstallManager
{
    /** Persisted operations that gate finalize + drive resume. */
    public const STEPS = [
        'requirements', 'database', 'environment', 'storage',
        'permissions', 'migrate', 'seed', 'mail', 'admin', 'finalize',
    ];

    /** The full storage tree the platform expects (created under storage/). */
    private const STORAGE_TREE = [
        'logs', 'cache', 'sessions', 'framework', 'backups',
        'app', 'app/uploads', 'app/exports', 'app/imports',
        'app/temp', 'app/pdf', 'app/reports',
    ];

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

    /** Completion percentage (0–100) for the progress bar. */
    public function progress(): int
    {
        $done = count(array_intersect(self::STEPS, $this->state()['completed']));

        return (int) round($done / count(self::STEPS) * 100);
    }

    // --- Step: requirements (System / Server / PHP Extensions) -------------

    /**
     * @return array{groups:array<string,array<int,array<string,mixed>>>,passed:bool}
     */
    public function checkRequirements(): array
    {
        $groups = [];

        // System / server.
        $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
        $groups['Server'][] = $this->check('PHP version ≥ 8.2', $phpOk, PHP_VERSION, true,
            'Switch the site to PHP 8.2 or newer from your hosting control panel (e.g. cPanel → "Select PHP Version").');

        $groups['Server'][] = $this->limitCheck('Memory limit ≥ 128M', 'memory_limit', 128,
            'Increase memory_limit to at least 128M in php.ini or your hosting PHP settings.');
        $groups['Server'][] = $this->limitCheck('Max execution time ≥ 30s', 'max_execution_time', 30,
            'Raise max_execution_time to 30 or more (0 = unlimited is fine) in your PHP settings.', true);
        $groups['Server'][] = $this->limitCheck('Upload size ≥ 8M', 'upload_max_filesize', 8,
            'Increase upload_max_filesize (and post_max_size) to at least 8M for résumé/file uploads.', false, false);
        $groups['Server'][] = $this->limitCheck('POST size ≥ 8M', 'post_max_size', 8,
            'Increase post_max_size to at least 8M in your PHP settings.', false, false);

        $free = @disk_free_space($this->basePath);
        $freeOk = $free === false ? true : $free > 256 * 1024 * 1024;
        $groups['Server'][] = $this->check('Free disk space', $freeOk,
            $free === false ? 'unknown' : $this->humanBytes((int) $free), false,
            'Free up disk space — at least a few hundred MB is recommended.');

        // PHP extensions.
        $required = ['pdo' => true, 'pdo_mysql' => true, 'mbstring' => true, 'openssl' => true,
            'json' => true, 'fileinfo' => true, 'curl' => true, 'xml' => true];
        $recommended = ['gd' => false, 'intl' => false, 'zip' => false, 'imagick' => false, 'redis' => false];
        foreach ($required as $ext => $req) {
            $groups['PHP Extensions'][] = $this->check("Extension: {$ext}", extension_loaded($ext),
                extension_loaded($ext) ? 'loaded' : 'missing', true,
                "Enable the PHP \"{$ext}\" extension from your hosting panel (PHP Extensions / php.ini).");
        }
        foreach ($recommended as $ext => $req) {
            $groups['PHP Extensions'][] = $this->check("Extension: {$ext} (recommended)", extension_loaded($ext),
                extension_loaded($ext) ? 'loaded' : 'missing', false,
                "Optional: enable the PHP \"{$ext}\" extension for full functionality (images, archives, search).");
        }

        // Storage writability (auto-created where possible).
        foreach (['storage', 'storage/logs', 'storage/cache', 'storage/framework'] as $dir) {
            $writable = $this->ensureWritable($this->basePath . '/' . $dir);
            $groups['Storage'][] = $this->check("Writable: /{$dir}", $writable,
                $writable ? 'writable' : 'not writable', true,
                'The web server user needs write access here — use the Permissions step\'s Auto-Fix, or set the folder to 775.');
        }
        $rootWritable = is_writable($this->basePath) || is_file($this->envPath);
        $groups['Storage'][] = $this->check('Writable: project root (.env)', $rootWritable,
            $rootWritable ? 'writable' : 'not writable', true,
            'The installer must write the .env file — make the project root writable (755/775) during setup.');

        $passed = true;
        foreach ($groups as $checks) {
            foreach ($checks as $c) {
                if ($c['required'] && ! $c['ok']) {
                    $passed = false;
                }
            }
        }
        if ($passed) {
            $this->markComplete('requirements');
        }

        return ['groups' => $groups, 'passed' => $passed];
    }

    /** @return array{name:string,ok:bool,value:string,required:bool,solution:string} */
    private function check(string $name, bool $ok, string $value, bool $required, string $solution): array
    {
        return ['name' => $name, 'ok' => $ok, 'value' => $value, 'required' => $required,
            'solution' => $ok ? '' : $solution];
    }

    private function limitCheck(string $name, string $ini, int $minMb, string $solution, bool $time = false, bool $required = true): array
    {
        $raw = (string) ini_get($ini);
        $bytes = $this->iniToBytes($raw);
        if ($time) {
            $val = (int) $raw;
            $ok = $val <= 0 || $val >= $minMb; // ≤0 = unlimited; for time $minMb is seconds
            return $this->check($name, $ok, $raw === '' ? 'n/a' : ($val <= 0 ? 'unlimited' : $raw . 's'), $required, $solution);
        }
        $ok = $bytes <= 0 || $bytes >= $minMb * 1024 * 1024; // ≤0 (e.g. -1) = unlimited
        return $this->check($name, $ok, $raw === '' ? 'n/a' : ($bytes < 0 ? 'unlimited' : $raw), $required, $solution);
    }

    private function iniToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $unit = strtolower($value[strlen($value) - 1]);
        $num = (int) $value;

        return match ($unit) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => (int) $value,
        };
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }

        return round($n, 1) . ' ' . $units[$i];
    }

    private function ensureWritable(string $path): bool
    {
        if (! is_dir($path)) {
            @mkdir($path, 0775, true);
        }

        return is_dir($path) && is_writable($path);
    }

    // --- Step: database ----------------------------------------------------

    /**
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

        try {
            $pdo = new PDO(
                "mysql:host={$host};port={$port};charset=utf8mb4",
                $username,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
        } catch (Throwable $e) {
            throw new RuntimeException('Could not connect to the database server. Check the host, port, username and password. (' . $e->getMessage() . ')');
        }

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$database}`");

        $this->markComplete('database', [
            'db' => compact('host', 'port', 'database', 'username', 'password'),
        ]);
    }

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

    // --- Step: environment (generate keys) ---------------------------------

    /**
     * Generate the application encryption key now so it is decided at the
     * Environment step; finalize writes it to .env. Idempotent.
     */
    public function generateEnvironment(): array
    {
        $state = $this->state();
        $key = $state['config']['app_key'] ?? Encrypter::generateKey();
        $this->markComplete('environment', ['app_key' => $key]);

        return ['app_key_set' => true];
    }

    // --- Step: storage -----------------------------------------------------

    /**
     * Create the full storage tree the platform expects. Idempotent.
     *
     * @return array<int,array{path:string,ok:bool}>
     */
    public function createStorage(): array
    {
        $results = [];
        foreach (self::STORAGE_TREE as $rel) {
            $path = $this->basePath . '/storage/' . $rel;
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            // Keep the tree in version control / uploads private to the web root.
            $gitignore = $this->basePath . '/storage/' . $rel . '/.gitignore';
            if (is_dir($path) && ! is_file($gitignore)) {
                @file_put_contents($gitignore, "*\n!.gitignore\n");
            }
            $results[] = ['path' => 'storage/' . $rel, 'ok' => is_dir($path)];
        }

        $allOk = ! in_array(false, array_column($results, 'ok'), true);
        if ($allOk) {
            $this->markComplete('storage');
        }

        return $results;
    }

    // --- Step: permissions (+ auto-fix) ------------------------------------

    /**
     * @return array{dirs:array<int,array{path:string,writable:bool}>,ok:bool}
     */
    public function checkPermissions(): array
    {
        $dirs = [];
        $ok = true;
        foreach (self::STORAGE_TREE as $rel) {
            $path = $this->basePath . '/storage/' . $rel;
            $writable = is_dir($path) && is_writable($path);
            $dirs[] = ['path' => 'storage/' . $rel, 'writable' => $writable];
            $ok = $ok && $writable;
        }
        if ($ok) {
            $this->markComplete('permissions');
        }

        return ['dirs' => $dirs, 'ok' => $ok];
    }

    /**
     * Attempt to repair non-writable storage folders automatically (no terminal).
     */
    public function fixPermissions(): array
    {
        foreach (self::STORAGE_TREE as $rel) {
            $path = $this->basePath . '/storage/' . $rel;
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            if (is_dir($path) && ! is_writable($path)) {
                @chmod($path, 0775);
            }
        }

        return $this->checkPermissions();
    }

    // --- Step: migrate -----------------------------------------------------

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

    // --- Step: seed --------------------------------------------------------

    public function runSeeders(): void
    {
        $seeder = require $this->basePath . '/database/seeders/DatabaseSeeder.php';
        $seeder->run($this->makeDatabase());
        $this->markComplete('seed');
    }

    // --- Step: mail (optional) --------------------------------------------

    /**
     * Persist mail configuration (used to write .env at finalize). Optional step.
     *
     * @param array{enabled?:bool,from_address?:string,from_name?:string} $cfg
     */
    public function configureMail(array $cfg): void
    {
        $this->markComplete('mail', ['mail' => [
            'enabled'      => ! empty($cfg['enabled']),
            'from_address' => trim((string) ($cfg['from_address'] ?? 'no-reply@halaops.local')),
            'from_name'    => trim((string) ($cfg['from_name'] ?? 'HalaOps')),
            'skipped'      => false,
        ]]);
    }

    public function skipMail(): void
    {
        $this->markComplete('mail', ['mail' => ['enabled' => false, 'skipped' => true]]);
    }

    /**
     * Send a test message using the entered settings. Never throws; returns a
     * human-readable result. When mail() is unavailable the message is logged so
     * the operator can still confirm the pipeline works.
     */
    public function sendTestEmail(string $to, array $cfg): array
    {
        $to = trim($to);
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Please enter a valid recipient email address.'];
        }

        $fromAddress = trim((string) ($cfg['from_address'] ?? 'no-reply@halaops.local'));
        $fromName = trim((string) ($cfg['from_name'] ?? 'HalaOps'));
        $logPath = $this->basePath . '/storage/logs';
        $enabled = ! empty($cfg['enabled']);

        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $fromAddress . '>',
        ]);
        $subject = 'HalaOps test email';
        $body = '<p>This is a test email from your HalaOps installer. If you received it, mail delivery works.</p>';

        $sent = false;
        if ($enabled && function_exists('mail')) {
            $sent = @mail($to, $subject, $body, $headers);
        }

        if (! is_dir($logPath)) {
            @mkdir($logPath, 0775, true);
        }
        @file_put_contents(
            $logPath . '/mail-' . date('Y-m-d') . '.log',
            sprintf("==== %s ====\nTo: %s\nSubject: %s\nDelivered: %s\n%s\n\n",
                date('Y-m-d H:i:s'), $to, $subject, $sent ? 'yes (mail())' : 'logged only', $body),
            FILE_APPEND | LOCK_EX
        );

        if ($sent) {
            return ['ok' => true, 'message' => "Test email sent to {$to} via the host mail transport."];
        }
        if ($enabled) {
            return ['ok' => true, 'message' => "Mail transport not confirmed; the message was written to storage/logs so you can verify the pipeline. Check the recipient inbox/spam."];
        }

        return ['ok' => true, 'message' => 'Mail is disabled — the message was written to storage/logs (no email sent). Enable mail to deliver for real.'];
    }

    // --- Step: admin -------------------------------------------------------

    /**
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

    // --- Health check ------------------------------------------------------

    /**
     * Post-install style health check, runnable before finalize. Never throws.
     *
     * @return array{checks:array<int,array{name:string,ok:bool,value:string}>,passed:bool}
     */
    public function healthCheck(): array
    {
        $checks = [];
        $add = function (string $name, bool $ok, string $value) use (&$checks): void {
            $checks[] = ['name' => $name, 'ok' => $ok, 'value' => $value];
        };

        // Database connectivity + schema.
        try {
            $db = $this->makeDatabase();
            $one = (int) $db->scalar('SELECT 1');
            $add('Database connection', $one === 1, 'connected');
            $tables = (int) $db->scalar(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE='BASE TABLE'"
            );
            $add('Database schema', $tables > 100, $tables . ' tables');
            $perms = (int) $db->scalar('SELECT COUNT(*) FROM permissions');
            $add('Permissions seeded', $perms > 0, $perms . ' permissions');
            $admin = (int) $db->scalar('SELECT COUNT(*) FROM users');
            $add('Administrator account', $admin > 0, $admin . ' user(s)');
        } catch (Throwable $e) {
            $add('Database connection', false, $e->getMessage());
        }

        // Storage + permissions.
        $perm = $this->checkPermissions();
        $add('Storage writable', $perm['ok'], $perm['ok'] ? 'all folders writable' : 'some folders not writable');

        // Disk space.
        $free = @disk_free_space($this->basePath);
        $add('Disk space', $free === false || $free > 64 * 1024 * 1024, $free === false ? 'unknown' : $this->humanBytes((int) $free));

        // Environment key.
        $add('Environment key', (string) ($this->state()['config']['app_key'] ?? '') !== '', 'generated');

        // Mail (informational — never fails the health check).
        $mail = $this->state()['config']['mail'] ?? null;
        $add('Mail configured', true, is_array($mail) ? ($mail['enabled'] ? 'enabled' : 'disabled/logged') : 'not configured');

        $passed = true;
        foreach ($checks as $c) {
            $passed = $passed && $c['ok'];
        }

        return ['checks' => $checks, 'passed' => $passed];
    }

    // --- Final validation (real, before declaring success) -----------------

    /**
     * Exercise the live system end-to-end against the configured database: read +
     * write, create a workspace/user/role/permission/setting and roll it all back,
     * a storage write, and a mail-pipeline probe. Never throws; reports per check.
     *
     * @return array{checks:array<int,array{name:string,ok:bool,value:string}>,passed:bool}
     */
    public function finalValidation(): array
    {
        $checks = [];
        $add = function (string $name, bool $ok, string $value = '') use (&$checks): void {
            $checks[] = ['name' => $name, 'ok' => $ok, 'value' => $value];
        };

        try {
            $db = $this->makeDatabase();

            // DB read.
            $add('Database read', (int) $db->scalar('SELECT 1') === 1, 'ok');

            // Roles & permissions present.
            $add('RBAC catalogue', (int) $db->scalar('SELECT COUNT(*) FROM permissions') > 0
                && (int) $db->scalar('SELECT COUNT(*) FROM roles') > 0, 'roles + permissions');

            // Lookups / reference data present.
            $add('Configuration data', (int) $db->scalar('SELECT COUNT(*) FROM lookup_values') > 0
                && (int) $db->scalar('SELECT COUNT(*) FROM currencies') > 0, 'lookups + reference');

            // DB write inside a rolled-back transaction (workspace + membership chain).
            $writeOk = false;
            $detail = '';
            try {
                $db->beginTransaction();
                $uStatus = (int) $db->scalar("SELECT lv.id FROM lookup_values lv JOIN lookup_categories lc ON lc.id=lv.category_id WHERE lc.`key`='user_status' AND lc.workspace_id IS NULL AND lv.`key`='active' AND lv.workspace_id IS NULL LIMIT 1");
                $uid = $db->table('users')->insertGetId([
                    'uuid' => $db->scalar('SELECT UUID()'), 'name' => 'Install Probe',
                    'email' => 'install-probe-' . substr(md5((string) mt_rand()), 0, 10) . '@halaops.local',
                    'password' => Hash::make('probe-' . mt_rand()), 'locale' => 'en',
                    'user_status_id' => $uStatus, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $wType = (int) $db->scalar("SELECT id FROM workspace_types ORDER BY sort_order LIMIT 1");
                $wStatus = (int) $db->scalar("SELECT id FROM workspace_statuses WHERE workspace_id IS NULL AND `key`='trial' LIMIT 1");
                $wid = $db->table('workspaces')->insertGetId([
                    'uuid' => $db->scalar('SELECT UUID()'), 'workspace_type_id' => $wType,
                    'name' => 'Install Probe WS', 'slug' => 'install-probe-' . substr(md5((string) mt_rand()), 0, 8),
                    'owner_id' => $uid, 'locale' => 'en', 'workspace_status_id' => $wStatus,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $writeOk = $wid > 0;
                $detail = 'insert ok (rolled back)';
                $db->rollBack();
            } catch (Throwable $e) {
                if ($db->inTransactionDepth() > 0) {
                    $db->rollBack();
                }
                $detail = $e->getMessage();
            }
            $add('Database write (create workspace/user, rolled back)', $writeOk, $detail);
        } catch (Throwable $e) {
            $add('Database read', false, $e->getMessage());
        }

        // Storage write probe.
        try {
            $probe = $this->basePath . '/storage/app/temp/install-probe.txt';
            @file_put_contents($probe, 'ok');
            $ok = is_file($probe) && file_get_contents($probe) === 'ok';
            @unlink($probe);
            $add('Storage write', $ok, 'storage/app/temp');
        } catch (Throwable $e) {
            $add('Storage write', false, $e->getMessage());
        }

        // Mail pipeline probe (logs at minimum).
        $mailCfg = $this->state()['config']['mail'] ?? ['enabled' => false];
        $mailRes = $this->sendTestEmail('install-probe@halaops.local', $mailCfg);
        $add('Mail pipeline', $mailRes['ok'], $mailRes['message']);

        $passed = true;
        foreach ($checks as $c) {
            $passed = $passed && $c['ok'];
        }

        return ['checks' => $checks, 'passed' => $passed];
    }

    // --- Step: finalize ----------------------------------------------------

    /**
     * @param array{app_name?:string,app_url?:string} $appData
     */
    public function finalize(array $appData): void
    {
        foreach (['database', 'environment', 'storage', 'permissions', 'migrate', 'seed', 'admin'] as $required) {
            if (! $this->isStepComplete($required)) {
                throw new RuntimeException("Cannot finalize: the '{$required}' step has not completed yet.");
            }
        }

        // Real final validation gate.
        $validation = $this->finalValidation();
        if (! $validation['passed']) {
            $failed = array_values(array_filter($validation['checks'], static fn ($c) => ! $c['ok']));
            $first = $failed[0]['name'] ?? 'unknown';
            throw new RuntimeException('Final validation failed at: ' . $first . '. The installation was not locked so you can fix it and retry.');
        }

        $state = $this->state();
        $db = $state['config']['db'] ?? [];
        $mail = $state['config']['mail'] ?? ['enabled' => false, 'from_address' => 'no-reply@halaops.local', 'from_name' => 'HalaOps'];
        $appUrl = rtrim($appData['app_url'] ?? '', '/');

        $env = [
            'APP_NAME'            => $appData['app_name'] ?? 'HalaOps',
            'APP_ENV'             => 'production',
            'APP_DEBUG'           => 'false',
            'APP_KEY'             => (string) ($state['config']['app_key'] ?? Encrypter::generateKey()),
            'APP_URL'             => $appUrl,
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
            '__MAIL__'            => '',
            'MAIL_ENABLED'        => ! empty($mail['enabled']) ? 'true' : 'false',
            'MAIL_FROM_ADDRESS'   => $mail['from_address'] ?? 'no-reply@halaops.local',
            'MAIL_FROM_NAME'      => $mail['from_name'] ?? 'HalaOps',
            '__SESSION__'         => '',
            'SESSION_SECURE'      => str_starts_with($appUrl, 'https') ? 'true' : 'false',
        ];

        $this->writeEnv($env);

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
        if ($value === '') {
            return '';
        }
        if (preg_match('/\s|#|"|\'/', $value)) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }

        return $value;
    }
}
