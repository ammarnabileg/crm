<?php

declare(strict_types=1);

/*
 * HaHireAI — self-contained browser setup.
 *
 * This file deliberately has NO framework/Composer dependency, so it is a
 * guaranteed-to-load fallback even on a misconfigured host. The app itself also
 * boots with no `vendor/` (bootstrap/autoload.php ships a PSR-4 fallback, and
 * composer.json has zero runtime deps), so `/install` works on a bare upload too —
 * this page is the belt-and-suspenders alternative. It offers two tabs:
 *
 *   1) Install  — database credentials + the first owner account (only).
 *   2) Terminal — a fixed, allow-listed set of setup commands (composer install,
 *      migrate, seed, build, fix permissions, diagnostics …). It is NOT an
 *      arbitrary shell: only the named commands below can run, and the whole
 *      page disables itself once setup is locked.
 *
 * Open it at:  https://your-domain/setup.php
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$ROOT = dirname(__DIR__);
$LOCK = $ROOT . '/storage/installed.lock';
$ENV = $ROOT . '/.env';
$installed = is_file($LOCK);

session_start();
if (empty($_SESSION['setup_token'])) {
    $_SESSION['setup_token'] = bin2hex(random_bytes(16));
}
$TOKEN = $_SESSION['setup_token'];

/** The ONLY commands the Terminal tab may run (no arbitrary input, ever). */
$COMMANDS = [
    'composer install' => 'Install PHP dependencies (creates vendor/)',
    'composer dump-autoload -o' => 'Rebuild the optimized autoloader',
    'php bin/console.php migrate' => 'Run database migrations',
    'php bin/console.php migrate:status' => 'Show migration status',
    'php bin/console.php db:seed' => 'Seed baseline data',
    'php bin/console.php health' => 'Run health checks',
    'npm install' => 'Install front-end build tools (optional)',
    'npm run build:css' => 'Build the stylesheet (optional)',
    'fix-permissions' => 'Make storage/ writable (chmod 775)',
    'php -v' => 'Show the PHP version',
    'php -m' => 'List loaded PHP extensions',
    'check-writable' => 'Check storage/ is writable',
    'disk-free' => 'Show free disk space',
];

/** Resolve a runnable invocation for an allow-listed key (handles host quirks). */
function resolve_command(string $key, string $root): ?array
{
    switch ($key) {
        case 'fix-permissions':
            return ['cmd' => 'chmod -R 775 ' . escapeshellarg($root . '/storage'), 'shell' => true];
        case 'check-writable':
            $w = is_writable($root . '/storage') ? 'WRITABLE' : 'NOT writable';
            return ['echo' => 'storage/ is ' . $w];
        case 'disk-free':
            $free = @disk_free_space($root);
            return ['echo' => $free ? round($free / 1048576) . ' MB free' : 'unknown'];
    }

    // composer / npm: try common shapes so it works across hosts.
    if (str_starts_with($key, 'composer ')) {
        $args = substr($key, strlen('composer '));
        foreach (['composer', 'composer.phar', '/usr/bin/composer', '/usr/local/bin/composer'] as $bin) {
            if ($bin === 'composer.phar' && ! is_file($root . '/composer.phar')) {
                continue;
            }
            $prefix = $bin === 'composer.phar' ? 'php ' . escapeshellarg($root . '/composer.phar') : $bin;
            return ['cmd' => $prefix . ' ' . $args . ' --no-interaction', 'shell' => true];
        }
    }
    if (str_starts_with($key, 'npm ')) {
        return ['cmd' => $key, 'shell' => true];
    }
    if (str_starts_with($key, 'php ')) {
        return ['cmd' => $key, 'shell' => true];
    }

    return null;
}

/** Run a resolved command and return [output, exitCode]. */
function run_resolved(array $r, string $root): array
{
    if (isset($r['echo'])) {
        return [$r['echo'], 0];
    }
    if (! function_exists('proc_open')) {
        return ["proc_open is disabled on this host — run this command via SSH:\n  " . ($r['cmd'] ?? ''), 1];
    }

    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = @proc_open($r['cmd'], $desc, $pipes, $root);
    if (! is_resource($proc)) {
        return ['Could not start the command.', 1];
    }
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    foreach ($pipes as $p) {
        is_resource($p) && fclose($p);
    }
    $code = proc_close($proc);

    return [trim($out) === '' ? '(no output)' : $out, $code];
}

/**
 * Run an allow-listed command. Prefers an IN-PROCESS PHP implementation so the
 * essential operations work even on shared hosts where proc_open/exec are disabled
 * (the common reason "the terminal does nothing"). Falls back to a real shell only
 * for things that genuinely need one (npm). Now possible because the app boots with
 * no Composer/vendor.
 */
function run_command(string $key, string $root): string
{
    switch ($key) {
        case 'php -v':
            return 'PHP ' . PHP_VERSION . ' on ' . PHP_OS . ' (' . PHP_SAPI . ')';
        case 'php -m':
            return implode("\n", get_loaded_extensions());
        case 'check-writable':
            return 'storage/ is ' . (is_writable($root . '/storage') ? 'WRITABLE ✓' : 'NOT writable ✗ — run “fix-permissions”.');
        case 'disk-free':
            $free = @disk_free_space($root);
            return $free ? round($free / 1048576) . ' MB free on disk' : 'Free space unknown.';
        case 'fix-permissions':
            return fix_permissions($root . '/storage');
        case 'composer install':
        case 'composer dump-autoload -o':
            return composer_status($root);
        case 'php bin/console.php migrate':
        case 'php bin/console.php migrate:status':
        case 'php bin/console.php db:seed':
        case 'php bin/console.php health':
            return boot_and_run($root, $key);
    }

    // Shell fallback (npm) — only when the host actually allows process control.
    $resolved = resolve_command($key, $root);
    if ($resolved === null) {
        return 'Could not resolve the command.';
    }
    [$out] = run_resolved($resolved, $root);

    return $out;
}

/** Recursively chmod storage/ to 0775 using pure PHP (no shell). */
function fix_permissions(string $dir): string
{
    if (! is_dir($dir)) {
        return 'storage/ not found at ' . $dir;
    }
    $changed = @chmod($dir, 0775) ? 1 : 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($it as $path) {
        if (@chmod((string) $path, is_dir((string) $path) ? 0775 : 0664)) {
            $changed++;
        }
    }

    return "Set permissions on {$changed} path(s) under storage/. " .
        (is_writable($dir) ? 'storage/ is now writable ✓' : 'storage/ is still not writable — set it to 775 from your host file manager.');
}

/** Composer is no longer required to run; report that clearly. */
function composer_status(string $root): string
{
    $has = is_dir($root . '/vendor');

    return "HaHireAI has zero runtime dependencies — Composer is NOT required to run.\n"
        . 'The app boots straight from the uploaded files via its built-in autoloader.'
        . ($has ? "\n(vendor/ is present, used only for the developer test suite.)"
                : "\nYou can install the platform now from the Install tab — no “composer install” needed.");
}

/**
 * Boot the framework in-process and run a maintenance command via the same
 * services the CLI uses. Works without a shell. DB commands need the credentials
 * from the Install tab to be present in .env.
 */
function boot_and_run(string $root, string $key): string
{
    try {
        /** @var \HaHireAI\Core\Kernel $kernel */
        $kernel = require $root . '/bootstrap/app.php';
        $kernel->boot();
        $c = $kernel->container();

        if ($key === 'php bin/console.php migrate') {
            $runner = $c->make(\HaHireAI\Core\Database\Migrations\MigrationRunner::class);
            $applied = $runner->run($kernel->basePath('database/migrations'));
            $synced = $c->make(\HaHireAI\Modules\Permissions\Application\PermissionSeeder::class)->seed();

            return ($applied === [] ? 'Nothing to migrate — schema is up to date.' : 'Applied ' . count($applied) . " migration(s):\n  " . implode("\n  ", $applied))
                . "\nSynced {$synced} permission(s) from the catalog.";
        }

        if ($key === 'php bin/console.php migrate:status') {
            $ran = $c->make(\HaHireAI\Core\Database\Migrations\MigrationRunner::class)->ranMigrations();

            return 'Ran migrations (' . count($ran) . "):\n  ✓ " . implode("\n  ✓ ", $ran);
        }

        if ($key === 'php bin/console.php db:seed') {
            $permissions = $c->make(\HaHireAI\Modules\Permissions\Application\PermissionSeeder::class)->seed();
            $c->make(\HaHireAI\Modules\Billing\Application\PlanService::class)->seedDefaults();

            return "Seeded {$permissions} permission(s) and the billing plan catalog.";
        }

        // health
        $report = $c->make(\HaHireAI\Core\Health\HealthChecker::class)->run();
        $lines = ['Overall: ' . $report['status']->value];
        foreach ($report['probes'] as $name => $p) {
            $lines[] = sprintf('  [%s] %s — %s', $p['status'], $name, $p['message']);
        }

        return implode("\n", $lines);
    } catch (Throwable $e) {
        return 'Error: ' . $e->getMessage()
            . "\n\nIf this is a database error, finish the Install tab first so the credentials exist in .env.";
    }
}

/** Minimal .env writer (no framework). Never overwrites an existing key value. */
function write_env(string $path, array $force, array $defaults): void
{
    $existing = is_file($path) ? (string) file_get_contents($path) : '';
    $values = $force;
    foreach ($defaults as $k => $v) {
        if (preg_match('/^' . preg_quote($k, '/') . '=.+/m', $existing) !== 1) {
            $values[$k] = $v;
        }
    }
    $lines = $existing === '' ? [] : (preg_split('/\r?\n/', $existing) ?: []);
    $seen = [];
    foreach ($lines as &$line) {
        if (preg_match('/^([A-Z0-9_]+)=/', (string) $line, $m) && array_key_exists($m[1], $values)) {
            $q = $values[$m[1]];
            $line = $m[1] . '=' . (preg_match('/\s|"|#/', $q) ? '"' . str_replace('"', '\"', $q) . '"' : $q);
            $seen[$m[1]] = true;
        }
    }
    unset($line);
    foreach ($values as $k => $v) {
        if (! isset($seen[$k])) {
            $lines[] = $k . '=' . (preg_match('/\s|"|#/', $v) ? '"' . str_replace('"', '\"', $v) . '"' : $v);
        }
    }
    file_put_contents($path, implode("\n", $lines) . "\n");
}

$tab = ($_GET['tab'] ?? 'install') === 'terminal' ? 'terminal' : 'install';
$flash = null;
$flashOk = false;
$terminalOut = null;
$terminalCmd = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ! $installed) {
    $okToken = hash_equals($TOKEN, (string) ($_POST['_token'] ?? ''));
    $action = (string) ($_POST['action'] ?? '');

    if (! $okToken) {
        $flash = 'Security token mismatch — reload the page and try again.';
    } elseif ($action === 'run') {
        $tab = 'terminal';
        $key = (string) ($_POST['command'] ?? '');
        if (! array_key_exists($key, $COMMANDS)) {
            $terminalOut = 'Command not allowed.';
        } else {
            $terminalCmd = $key;
            $terminalOut = run_command($key, $ROOT);
        }
    } elseif ($action === 'install') {
        $db = [
            'host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')),
            'port' => (int) ($_POST['db_port'] ?? 3306),
            'database' => trim((string) ($_POST['db_database'] ?? '')),
            'username' => trim((string) ($_POST['db_username'] ?? '')),
            'password' => (string) ($_POST['db_password'] ?? ''),
        ];
        $owner = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
        ];

        try {
            if ($owner['name'] === '' || ! filter_var($owner['email'], FILTER_VALIDATE_EMAIL) || strlen($owner['password']) < 8) {
                throw new RuntimeException('Enter a name, a valid email, and a password of at least 8 characters.');
            }

            // 1) Verify the database credentials.
            new PDO(
                "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset=utf8mb4",
                $db['username'],
                $db['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
            );

            // 2) Persist config (DB + a generated app key).
            write_env($ENV, [
                'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_DATABASE' => $db['database'],
                'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'],
            ], [
                'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
                'APP_ENV' => 'production', 'APP_DEBUG' => 'false',
            ]);

            // 3) Boot the framework reading the .env we just wrote, then run the
            //    real install: migrate, seed, owner, lock. No Composer needed —
            //    HaHireAI has zero runtime dependencies and ships its own PSR-4
            //    autoloader fallback (bootstrap/autoload.php), so it runs straight
            //    from the uploaded files.
            /** @var \HaHireAI\Core\Kernel $kernel */
            $kernel = require $ROOT . '/bootstrap/app.php';
            $kernel->boot();
            $installer = $kernel->container()->make(\HaHireAI\Modules\Installer\Application\Installer::class);
            $installer->install($owner);

            header('Location: /login');
            exit;
        } catch (Throwable $e) {
            $flash = $e->getMessage();
        }
    }
}

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HaHireAI — Setup</title>
    <style>
        :root { --b: #2563eb; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; background: #f1f5f9; color: #1e293b; }
        .wrap { max-width: 720px; margin: 40px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 28px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
        .brand { display: flex; align-items: center; gap: 10px; justify-content: center; margin-bottom: 6px; }
        .badge { width: 34px; height: 34px; border-radius: 9px; background: var(--b); color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        p.sub { color: #64748b; font-size: 14px; margin: 0 0 20px; }
        .tabs { display: flex; gap: 6px; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; }
        .tabs a { padding: 9px 14px; font-size: 14px; font-weight: 600; color: #64748b; text-decoration: none; border-bottom: 2px solid transparent; }
        .tabs a.on { color: var(--b); border-bottom-color: var(--b); }
        label { display: block; font-size: 13px; font-weight: 500; margin: 12px 0 4px; }
        input { width: 100%; padding: 9px 11px; font-size: 14px; border: 1px solid #cbd5e1; border-radius: 9px; }
        .row { display: flex; gap: 10px; } .row > div { flex: 1; }
        .h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; margin: 18px 0 2px; font-weight: 700; }
        button { cursor: pointer; }
        .btn { margin-top: 18px; width: 100%; padding: 11px; font-size: 14px; font-weight: 600; color: #fff; background: var(--b); border: 0; border-radius: 9px; }
        .cmds { display: flex; flex-wrap: wrap; gap: 8px; }
        .cmds button { font-family: ui-monospace, monospace; font-size: 12px; padding: 7px 10px; border: 1px solid #cbd5e1; background: #fff; border-radius: 8px; color: #334155; }
        pre { margin-top: 14px; background: #0f172a; color: #6ee7b7; padding: 14px; border-radius: 10px; font-size: 12px; line-height: 1.5; max-height: 360px; overflow: auto; white-space: pre-wrap; }
        .flash { padding: 11px 14px; border-radius: 9px; font-size: 14px; margin-bottom: 16px; }
        .err { background: #fef2f2; color: #b91c1c; } .ok { background: #ecfdf5; color: #047857; }
        .note { font-size: 12px; color: #94a3b8; margin-top: 10px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand"><span class="badge">Ha</span><strong style="font-size:20px;">HaHire<span style="color:var(--b)">AI</span></strong></div>
    <p class="sub" style="text-align:center;">Setup</p>
    <div class="card">
        <?php if ($installed): ?>
            <h1>Already installed</h1>
            <p class="sub">Setup is locked. Delete <code>storage/installed.lock</code> only if you intend to reinstall.</p>
            <a class="btn" style="display:block;text-align:center;text-decoration:none;" href="/login">Go to sign in →</a>
        <?php else: ?>
            <div class="tabs">
                <a href="?tab=install" class="<?= $tab === 'install' ? 'on' : '' ?>">Install</a>
                <a href="?tab=terminal" class="<?= $tab === 'terminal' ? 'on' : '' ?>">Terminal</a>
            </div>

            <?php if ($flash !== null): ?><div class="flash <?= $flashOk ? 'ok' : 'err' ?>"><?= $h($flash) ?></div><?php endif; ?>

            <?php if ($tab === 'install'): ?>
                <h1>Install HaHireAI</h1>
                <p class="sub">Enter your database and owner account. That's all — the page does the rest.</p>
                <form method="post" action="?tab=install">
                    <input type="hidden" name="_token" value="<?= $h($TOKEN) ?>">
                    <input type="hidden" name="action" value="install">
                    <div class="h2">Database</div>
                    <div class="row">
                        <div style="flex:2"><label>Host</label><input name="db_host" value="127.0.0.1" required></div>
                        <div><label>Port</label><input name="db_port" value="3306" required></div>
                    </div>
                    <label>Database name</label><input name="db_database" required>
                    <div class="row">
                        <div><label>Username</label><input name="db_username" required></div>
                        <div><label>Password</label><input name="db_password" type="password"></div>
                    </div>
                    <div class="h2">First owner</div>
                    <label>Full name</label><input name="name" required>
                    <label>Email</label><input name="email" type="email" required>
                    <label>Password</label><input name="password" type="password" minlength="8" required>
                    <button class="btn" type="submit">Run installation</button>
                    <p class="note">No Composer or terminal needed — this runs the database setup and creates your owner account straight away.</p>
                </form>
            <?php else: ?>
                <h1>Terminal</h1>
                <p class="sub">Run a setup command and see its output. Fixed, safe commands only — not an open shell. The core commands run in-process, so they work even when your host blocks <code>proc_open</code>/<code>exec</code>.</p>
                <div class="cmds">
                    <?php foreach ($COMMANDS as $key => $desc): ?>
                        <form method="post" action="?tab=terminal" style="margin:0;">
                            <input type="hidden" name="_token" value="<?= $h($TOKEN) ?>">
                            <input type="hidden" name="action" value="run">
                            <input type="hidden" name="command" value="<?= $h($key) ?>">
                            <button type="submit" title="<?= $h($desc) ?>"><?= $h($key) ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
                <pre><?php if ($terminalCmd !== null): ?>$ <?= $h($terminalCmd) . "\n" . $h((string) $terminalOut) ?><?php else: ?>Output appears here.<?php endif; ?></pre>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
