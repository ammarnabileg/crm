<?php

declare(strict_types=1);

/*
 * Minimal CLI console for development/operations. The zero-touch installer
 * (Phase 8) performs the same steps from the browser — see docs/BUILD_SYSTEM.md.
 *
 * Usage:
 *   php bin/console.php migrate            run pending migrations
 *   php bin/console.php migrate:rollback   roll back the last batch
 *   php bin/console.php migrate:status     show ran vs pending
 *   php bin/console.php db:wipe            drop all tables (DESTRUCTIVE)
 *   php bin/console.php db:seed            seed permission catalog + billing plans
 *   php bin/console.php health             run health probes
 */

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Health\HealthChecker;
use HaHireAI\Modules\Billing\Application\PlanService;
use HaHireAI\Modules\Permissions\Application\PermissionSeeder;

/** @var \HaHireAI\Core\Kernel $kernel */
$kernel = require dirname(__DIR__) . '/bootstrap/app.php';
$kernel->boot();

$command = $argv[1] ?? 'help';
$migrationsDir = $kernel->basePath('database/migrations');
$runner = $kernel->container()->make(MigrationRunner::class);
$connection = $kernel->container()->make(Connection::class);

$out = static fn (string $line) => fwrite(STDOUT, $line . PHP_EOL);

switch ($command) {
    case 'migrate':
        $applied = $runner->run($migrationsDir, $out);
        $out($applied === [] ? 'Nothing to migrate.' : 'Migrated ' . count($applied) . ' migration(s).');
        break;

    case 'migrate:rollback':
        $rolled = $runner->rollback($migrationsDir);
        $out($rolled === [] ? 'Nothing to roll back.' : 'Rolled back: ' . implode(', ', $rolled));
        break;

    case 'migrate:status':
        $ran = $runner->ranMigrations();
        $out('Ran migrations (' . count($ran) . '):');
        foreach ($ran as $m) {
            $out('  ✓ ' . $m);
        }
        break;

    case 'db:wipe':
        $connection->unprepared('SET FOREIGN_KEY_CHECKS=0');
        $tables = $connection->select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()');
        foreach ($tables as $row) {
            $connection->unprepared('DROP TABLE IF EXISTS `' . $row['t'] . '`');
        }
        $connection->unprepared('SET FOREIGN_KEY_CHECKS=1');
        $out('Dropped ' . count($tables) . ' table(s).');
        break;

    case 'db:seed':
        $permissions = $kernel->container()->make(PermissionSeeder::class)->seed();
        $kernel->container()->make(PlanService::class)->seedDefaults();
        $out("Seeded {$permissions} permission(s) and the billing plan catalog.");
        break;

    case 'health':
        $report = $kernel->container()->make(HealthChecker::class)->run();
        $out('Overall: ' . $report['status']->value);
        foreach ($report['probes'] as $name => $p) {
            $out(sprintf('  [%s] %s — %s', $p['status'], $name, $p['message']));
        }
        break;

    default:
        $out('Commands: migrate | migrate:rollback | migrate:status | db:wipe | health');
}
