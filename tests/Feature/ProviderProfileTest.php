<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\AiEngine\Application\ProviderProfileService;
use HaHireAI\Shared\Encrypter;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** AI Provider Profiles (Phase B): encryption, single default, isolation. */
final class ProviderProfileTest extends TestCase
{
    private Connection $connection;
    private ProviderProfileService $profiles;

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
        $this->profiles = new ProviderProfileService($this->connection, new Encrypter('test-app-key-0123456789'));
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_profiles_encrypt_keys_and_track_a_single_default(): void
    {
        $ws = $this->workspace();
        $a = $this->profiles->create($ws, 'OpenAI Prod', 'openai', 'gpt-4o', 'sk-secret-A');
        $b = $this->profiles->create($ws, 'Anthropic', 'anthropic', 'claude', 'sk-secret-B');

        $list = $this->profiles->list($ws);
        $this->assertCount(2, $list);
        foreach ($list as $p) {
            $this->assertArrayNotHasKey('encrypted_key', $p);
        }

        // First profile is the default; key decrypts for use.
        $default = $this->profiles->getDefault($ws);
        $this->assertSame('OpenAI Prod', $default['name']);
        $this->assertSame('sk-secret-A', $default['key']);

        // Switch the default.
        $this->assertTrue($this->profiles->setDefault($ws, $b));
        $this->assertSame('sk-secret-B', $this->profiles->getDefault($ws)['key']);

        // Ciphertext at rest is not the plaintext.
        $row = $this->connection->selectOne('SELECT encrypted_key FROM ai_provider_profiles WHERE id = ?', [$a]);
        $this->assertNotSame('sk-secret-A', (string) $row['encrypted_key']);

        // Per-workspace isolation.
        $this->assertNull($this->profiles->getDefault($this->workspace()));
    }

    private function workspace(): string
    {
        $owner = Ulid::generate();
        $ws = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement('INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$owner, 'U', strtolower($owner) . '@e.test', 'x', $now, $now]);
        $this->connection->statement('INSERT INTO workspaces (id, name, slug, owner_user_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$ws, 'Acme', 'acme-' . strtolower($ws), $owner, 'active', $now, $now]);

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
