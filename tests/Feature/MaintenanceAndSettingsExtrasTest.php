<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Workspaces\Application\WorkspacePreferences;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Gaps D+E — maintenance (enabled/message/allow-IPs) and settings extras persist correctly. */
final class MaintenanceAndSettingsExtrasTest extends TestCase
{
    private Connection $connection;
    private WorkspacePreferences $prefs;
    private string $ws = '';

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
        $this->prefs = new WorkspacePreferences($this->connection);
        $this->ws = $this->workspace();
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_maintenance_toggle_and_allow_ips_persist(): void
    {
        $this->assertFalse($this->prefs->bool($this->ws, 'maintenance.enabled'));

        $this->prefs->set($this->ws, 'maintenance.message', "We'll be right back.");
        $this->prefs->set($this->ws, 'maintenance.allow_ips', '203.0.113.5,198.51.100.9');
        $this->prefs->set($this->ws, 'maintenance.enabled', '1');

        $this->assertTrue($this->prefs->bool($this->ws, 'maintenance.enabled'));
        $this->assertSame("We'll be right back.", $this->prefs->get($this->ws, 'maintenance.message'));
        $allow = array_map('trim', explode(',', (string) $this->prefs->get($this->ws, 'maintenance.allow_ips')));
        $this->assertContains('203.0.113.5', $allow);
        $this->assertContains('198.51.100.9', $allow);

        $this->prefs->set($this->ws, 'maintenance.enabled', '0');
        $this->assertFalse($this->prefs->bool($this->ws, 'maintenance.enabled'));
    }

    public function test_settings_extra_fields_round_trip(): void
    {
        $values = [
            'general.date_format' => 'd/m/Y',
            'company.contact_email' => 'careers@acme.com',
            'company.contact_phone' => '+20 100 000 0000',
            'brand.logo_text' => 'ACME',
        ];
        foreach ($values as $k => $v) {
            $this->prefs->set($this->ws, $k, $v);
        }
        foreach ($values as $k => $v) {
            $this->assertSame($v, $this->prefs->get($this->ws, $k), "pref {$k}");
        }
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

    private function wipe(): void
    {
        $this->connection->unprepared('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->connection->select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()') as $row) {
            $this->connection->unprepared('DROP TABLE IF EXISTS `' . $row['t'] . '`');
        }
        $this->connection->unprepared('SET FOREIGN_KEY_CHECKS=1');
    }
}
