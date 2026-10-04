<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Container\Container;
use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Core\Events\Dispatcher;
use HaHireAI\Modules\Observability\Application\BackupService;
use HaHireAI\Modules\Observability\Application\ErrorTracker;
use HaHireAI\Modules\Observability\Application\MetricsService;
use HaHireAI\Modules\Observability\Application\MonitorService;
use HaHireAI\Modules\Observability\ObservabilityModule;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Phase 15 — observability services on live MySQL 8. */
final class ObservabilityTest extends TestCase
{
    private Connection $connection;
    private string $backupDir;

    protected function setUp(): void
    {
        $this->connection = new Connection([
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_DATABASE') ?: 'hahireai_test',
            'username' => getenv('DB_USERNAME') ?: 'hahireai',
            'password' => getenv('DB_PASSWORD') ?: 'hahireai_pw',
            'charset' => 'utf8mb4',
        ]);

        try {
            $this->connection->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL test database unavailable: ' . $e->getMessage());
        }

        $this->wipe();
        (new MigrationRunner($this->connection, new SchemaBuilder($this->connection)))->run(dirname(__DIR__, 2) . '/database/migrations');
        $this->backupDir = sys_get_temp_dir() . '/hahireai-backup-' . substr(Ulid::generate(), -8);
    }

    protected function tearDown(): void
    {
        $this->wipe();
        if (is_dir($this->backupDir)) {
            foreach (glob($this->backupDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->backupDir);
        }
    }

    public function test_metrics_snapshot_reports_platform_totals(): void
    {
        $this->workspace();
        $snapshot = (new MetricsService($this->connection))->platformSnapshot();

        $this->assertSame(1, $snapshot['tenants']['workspaces']);
        $this->assertGreaterThanOrEqual(1, $snapshot['users']['total']);
        $this->assertSame(0, $snapshot['subscriptions']['active']);
        $this->assertArrayHasKey('mrr_cents', $snapshot['subscriptions']);
        $this->assertArrayHasKey('errors_24h', $snapshot['health']);
    }

    public function test_error_tracker_records_and_lists(): void
    {
        $tracker = new ErrorTracker($this->connection);
        $tracker->record(['message' => 'boom one', 'exception_class' => 'RuntimeException', 'file' => '/x.php', 'line' => 10]);
        $tracker->record(['message' => 'boom two', 'exception_class' => 'LogicException']);

        $this->assertCount(2, $tracker->recent());
        $this->assertSame(2, $tracker->countSince(time() - 60));
        $this->assertSame('boom two', $tracker->recent(1)[0]['message']); // newest first
    }

    public function test_monitor_opens_then_resolves_an_alert(): void
    {
        $tracker = new ErrorTracker($this->connection);
        $monitors = new MonitorService($this->connection);
        for ($i = 0; $i < 10; $i++) {
            $tracker->record(['message' => "err {$i}"]);
        }

        $first = $monitors->tick();
        $this->assertGreaterThanOrEqual(1, $first['opened']);
        $keys = array_map(static fn (array $a): string => (string) $a['monitor_key'], $monitors->openAlerts());
        $this->assertContains('errors.spike_1h', $keys);

        // Idempotent — a second tick opens no duplicate.
        $this->assertSame(0, $monitors->tick()['opened']);

        // Condition clears → the alert auto-resolves.
        $this->connection->unprepared('DELETE FROM error_events');
        $resolved = $monitors->tick();
        $this->assertGreaterThanOrEqual(1, $resolved['resolved']);
        $this->assertSame([], array_filter($monitors->openAlerts(), static fn (array $a): bool => $a['monitor_key'] === 'errors.spike_1h'));
    }

    public function test_backup_writes_a_manifest_and_records_completion(): void
    {
        $this->workspace();
        $backups = new BackupService($this->connection, $this->backupDir);

        $result = $backups->run();

        $this->assertSame('completed', $result['status']);
        $this->assertNotNull($result['path']);
        $this->assertFileExists($result['path']);

        $manifest = json_decode((string) file_get_contents($result['path']), true);
        $this->assertArrayHasKey('workspaces', $manifest['tables']);
        $this->assertArrayHasKey('error_events', $manifest['tables']);

        $recent = $backups->recent();
        $this->assertCount(1, $recent);
        $this->assertSame('completed', $recent[0]['status']);
    }

    public function test_module_listener_persists_dispatched_errors(): void
    {
        $container = new Container();
        $container->instance(Connection::class, $this->connection);
        $container->instance(EventDispatcher::class, new Dispatcher());

        (new ObservabilityModule())->boot($container);

        $events = $container->make(EventDispatcher::class);
        $this->assertTrue($events->hasListeners('system.error'));

        $events->dispatch('system.error', ['message' => 'kernel blew up', 'exception_class' => 'ErrorException', 'file' => '/k.php', 'line' => 5]);

        $this->assertSame(1, (new ErrorTracker($this->connection))->countSince(time() - 60));
    }

    private function workspace(): string
    {
        $userId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, 'Owner', 'owner-' . substr($userId, -6) . '@x.co', 'x', $now, $now],
        );

        $workspaceId = Ulid::generate();
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$workspaceId, 'Acme', 'acme-' . substr($workspaceId, -6), $userId, 'active', $now, $now],
        );

        return $workspaceId;
    }

    private function wipe(): void
    {
        $this->connection->unprepared('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->connection->select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()') as $row) {
            $this->connection->unprepared('DROP TABLE IF EXISTS `' . $row['t'] . '`');
        }
        $this->connection->unprepared('SET FOREIGN_KEY_CHECKS=1');
    }
}
