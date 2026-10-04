<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\AiEngine\Application\AiAnalyticsService;
use HaHireAI\Modules\AiEngine\Application\AiEngine;
use HaHireAI\Modules\AiEngine\Application\AiSettingsService;
use HaHireAI\Modules\AiEngine\Application\PromptEngine;
use HaHireAI\Modules\AiEngine\Application\ProviderRegistry;
use HaHireAI\Modules\AiEngine\Infrastructure\Providers\EchoProvider;
use HaHireAI\Shared\Encrypter;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Per-workspace AI analytics on live MySQL 8. */
final class AiAnalyticsTest extends TestCase
{
    private Connection $connection;
    private AiEngine $engine;
    private AiAnalyticsService $analytics;

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

        $registry = new ProviderRegistry();
        $registry->register(new EchoProvider());
        $prompts = new PromptEngine($this->connection);
        $prompts->seedDefaults();
        $this->engine = new AiEngine($this->connection, $registry, $prompts, new AiSettingsService($this->connection, new Encrypter('base64:' . base64_encode(str_repeat('k', 32)))));
        $this->analytics = new AiAnalyticsService($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_workspace_analytics_aggregate_ai_usage(): void
    {
        $ws = $this->workspace();
        $this->engine->run($ws, 'summarize_candidate', ['name' => 'A']);
        $this->engine->run($ws, 'summarize_candidate', ['name' => 'B']);
        $this->engine->run($ws, 'generate_job_description', ['title' => 'PHP']);

        $a = $this->analytics->workspaceAnalytics($ws);

        $this->assertSame(3, $a['totals']['runs']);
        $this->assertGreaterThan(0, $a['totals']['tokens']);
        $this->assertGreaterThanOrEqual(2, count($a['by_capability']));
        $this->assertNotSame([], $a['by_provider']);
        $this->assertSame('echo', $a['by_provider'][0]['provider']);
        $this->assertCount(3, $a['recent']);

        // Isolation: another workspace sees nothing.
        $this->assertSame(0, $this->analytics->workspaceAnalytics($this->workspace())['totals']['runs']);
    }

    private function workspace(): string
    {
        $userId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, 'Owner', 'o-' . substr($userId, -6) . '@x.co', 'x', $now, $now],
        );
        $workspaceId = Ulid::generate();
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$workspaceId, 'Acme', 'acme-' . substr($workspaceId, -6), $userId, $now, $now],
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
