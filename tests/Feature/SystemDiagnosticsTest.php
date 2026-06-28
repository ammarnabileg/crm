<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Observability\Application\SystemDiagnostics;
use HaHireAI\Modules\Workspaces\Application\WorkspacePreferences;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Sprint 3.3g-ii — read-only system diagnostics report observed infrastructure facts. */
final class SystemDiagnosticsTest extends TestCase
{
    private Connection $connection;
    private SystemDiagnostics $diagnostics;

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
        $this->diagnostics = new SystemDiagnostics($this->connection, sys_get_temp_dir(), dirname(__DIR__, 2));
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_reports_all_panels_with_real_facts(): void
    {
        $panels = $this->diagnostics->panels(['https' => true, 'forwarded_proto' => 'https', 'host' => 'app.test']);

        $byKey = [];
        foreach ($panels as $p) {
            $byKey[$p['key']] = $p;
            $this->assertNotEmpty($p['items'], "panel {$p['key']} has items");
        }

        $this->assertEqualsCanonicalizing(
            ['database', 'storage', 'cache', 'queue', 'mail', 'ssl', 'workers', 'runtime'],
            array_keys($byKey),
        );

        // Database: reachable, MySQL version detected, schema has tables.
        $this->assertSame('ok', $byKey['database']['status']);
        $db = $this->flatten($byKey['database']);
        $this->assertSame('MySQL', $db['Driver']);
        $this->assertNotSame('', $db['Version']);
        $this->assertGreaterThan(0, (int) $db['Tables']);

        // Runtime reports the live PHP version.
        $this->assertSame(PHP_VERSION, $this->flatten($byKey['runtime'])['PHP']);
    }

    public function test_transport_status_tracks_scheme(): void
    {
        $secure = $this->panelByKey($this->diagnostics->panels(['https' => true]), 'ssl');
        $this->assertSame('ok', $secure['status']);

        $insecure = $this->panelByKey($this->diagnostics->panels(['https' => false]), 'ssl');
        $this->assertSame('warn', $insecure['status']);
        $this->assertSame('http', $this->flatten($insecure)['Scheme']);
    }

    public function test_mail_panel_counts_configured_workspaces(): void
    {
        $before = $this->flatten($this->panelByKey($this->diagnostics->panels(), 'mail'));
        $this->assertSame('0', $before['Workspaces configured']);

        $ws = $this->workspace();
        (new WorkspacePreferences($this->connection))->set($ws, 'mail.smtp_host', 'smtp.acme.com');

        $after = $this->panelByKey($this->diagnostics->panels(), 'mail');
        $this->assertSame('ok', $after['status']);
        $this->assertSame('1', $this->flatten($after)['Workspaces configured']);
    }

    public function test_queue_panel_warns_on_failed_executions(): void
    {
        $ok = $this->panelByKey($this->diagnostics->panels(), 'queue');
        $this->assertSame('ok', $ok['status']);
        $this->assertSame('0', $this->flatten($ok)['Failed']);

        $this->insertFailedExecution($this->workspace());

        $warn = $this->panelByKey($this->diagnostics->panels(), 'queue');
        $this->assertSame('warn', $warn['status']);
        $this->assertSame('1', $this->flatten($warn)['Failed']);
    }

    /**
     * @param  array{key:string,label:string,status:string,items:list<array{k:string,v:string}>}  $panel
     * @return array<string,string>
     */
    private function flatten(array $panel): array
    {
        $out = [];
        foreach ($panel['items'] as $item) {
            $out[$item['k']] = $item['v'];
        }

        return $out;
    }

    /**
     * @param  list<array{key:string,label:string,status:string,items:list<array{k:string,v:string}>}>  $panels
     * @return array{key:string,label:string,status:string,items:list<array{k:string,v:string}>}
     */
    private function panelByKey(array $panels, string $key): array
    {
        foreach ($panels as $p) {
            if ($p['key'] === $key) {
                return $p;
            }
        }

        $this->fail("panel {$key} not found");
    }

    private function workspace(): string
    {
        $owner = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement('INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$owner, 'U', 'o' . substr($owner, -5) . '@x.co', 'x', $now, $now]);
        $ws = Ulid::generate();
        $this->connection->statement('INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$ws, 'Acme', 'acme-' . substr($ws, -6), $owner, $now, $now]);

        return $ws;
    }

    private function insertFailedExecution(string $ws): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->unprepared('SET FOREIGN_KEY_CHECKS=0');
        $this->connection->statement(
            "INSERT INTO workflow_executions (id, workspace_id, workflow_id, trigger_event, status, steps_total, steps_done, started_at, created_at)
             VALUES (?, ?, ?, ?, 'failed', 1, 0, ?, ?)",
            [Ulid::generate(), $ws, Ulid::generate(), 'application.submitted', $now, $now],
        );
        $this->connection->unprepared('SET FOREIGN_KEY_CHECKS=1');
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
