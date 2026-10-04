<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\AiEngine\Application\AiEngine;
use HaHireAI\Modules\AiEngine\Application\AiSettingsService;
use HaHireAI\Modules\AiEngine\Application\Exceptions\AiException;
use HaHireAI\Modules\AiEngine\Application\PromptEngine;
use HaHireAI\Modules\AiEngine\Application\ProviderRegistry;
use HaHireAI\Modules\AiEngine\Contracts\AiProvider;
use HaHireAI\Modules\AiEngine\Domain\AiResponse;
use HaHireAI\Modules\AiEngine\Infrastructure\Providers\EchoProvider;
use HaHireAI\Shared\Encrypter;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Phase 11 — the central AI Engine against a live MySQL 8 database. */
final class AiEngineTest extends TestCase
{
    private Connection $connection;
    private AiSettingsService $settings;
    private ProviderRegistry $registry;
    private AiEngine $engine;

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

        $encrypter = new Encrypter('base64:' . base64_encode(str_repeat('k', 32)));
        $this->settings = new AiSettingsService($this->connection, $encrypter);
        $this->registry = new ProviderRegistry();
        $this->registry->register(new EchoProvider());
        $prompts = new PromptEngine($this->connection);
        $prompts->seedDefaults();
        $this->engine = new AiEngine($this->connection, $this->registry, $prompts, $this->settings);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_run_capability_records_session_and_usage(): void
    {
        $ws = $this->workspace();

        $result = $this->engine->run($ws, 'summarize_candidate', ['name' => 'Sara', 'notes' => 'Great PHP']);

        $this->assertNotSame('', $result->text);
        $this->assertSame('echo', $result->provider);
        $this->assertGreaterThan(0, $result->inputTokens + $result->outputTokens);

        $session = $this->connection->selectOne('SELECT * FROM ai_sessions WHERE workspace_id = ?', [$ws]);
        $this->assertSame('completed', $session['status']);
        $this->assertSame('summarize_candidate', $session['capability']);

        $usage = $this->engine->usageSummary($ws);
        $this->assertSame(1, $usage['sessions']);
        $this->assertGreaterThan(0, $usage['tokens']);
    }

    public function test_falls_back_to_secondary_provider_when_primary_fails(): void
    {
        $ws = $this->workspace();
        $this->registry->register($this->failingProvider('boom'));
        $this->settings->setProvider($ws, 'boom', null, 'echo');

        $result = $this->engine->run($ws, 'summarize_candidate', ['name' => 'Sara']);

        $this->assertSame('echo', $result->provider);
        $this->assertSame('boom', $result->fallbackFrom);

        $session = $this->connection->selectOne('SELECT * FROM ai_sessions WHERE workspace_id = ?', [$ws]);
        $this->assertSame('boom', $session['fallback_from']);
        $this->assertSame('completed', $session['status']);
    }

    public function test_failure_without_fallback_records_failed_session_and_throws(): void
    {
        $ws = $this->workspace();
        $this->registry->register($this->failingProvider('boom'));
        $this->settings->setProvider($ws, 'boom');

        try {
            $this->engine->run($ws, 'summarize_candidate');
            $this->fail('Expected AiException');
        } catch (AiException $e) {
            $this->assertStringContainsString('failed', $e->getMessage());
        }

        $session = $this->connection->selectOne('SELECT status FROM ai_sessions WHERE workspace_id = ?', [$ws]);
        $this->assertSame('failed', $session['status']);
    }

    public function test_provider_keys_are_encrypted_at_rest(): void
    {
        $ws = $this->workspace();
        $this->settings->setKey($ws, 'openai', 'sk-super-secret-123');

        // Round-trips for use, but is ciphertext in the database.
        $this->assertSame('sk-super-secret-123', $this->settings->getKey($ws, 'openai'));

        $stored = $this->connection->selectOne('SELECT encrypted_key, key_hint FROM ai_keys WHERE workspace_id = ?', [$ws]);
        $this->assertStringNotContainsString('sk-super-secret-123', (string) $stored['encrypted_key']);
        $this->assertSame('••••-123', $stored['key_hint']);
    }

    public function test_provider_switching(): void
    {
        $ws = $this->workspace();
        $this->settings->setProvider($ws, 'echo', 'echo-1', 'echo');

        $config = $this->settings->forWorkspace($ws);
        $this->assertSame('echo', $config['provider']);
        $this->assertSame('echo-1', $config['model']);
    }

    private function failingProvider(string $key): AiProvider
    {
        return new class ($key) implements AiProvider {
            public function __construct(private string $key)
            {
            }

            public function key(): string
            {
                return $this->key;
            }

            public function complete(string $prompt, array $options = []): AiResponse
            {
                throw new \RuntimeException('provider down');
            }
        };
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
