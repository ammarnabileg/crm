<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\AiEngine\Application\AiCapabilityService;
use HaHireAI\Modules\AiEngine\Application\AiSettingsService;
use HaHireAI\Shared\Encrypter;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * AI features are gated on the workspace's own keys: OpenAI ⇒ interviews + CV
 * analysis; HeyGen (+ OpenAI) ⇒ video. Keys are per-workspace and isolated.
 */
final class AiCapabilityTest extends TestCase
{
    private Connection $connection;
    private AiSettingsService $settings;
    private AiCapabilityService $caps;

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
        $this->settings = new AiSettingsService($this->connection, new Encrypter('base64:' . base64_encode(str_repeat('k', 32))));
        $this->caps = new AiCapabilityService($this->settings);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_features_off_without_keys_then_enable_as_keys_are_added(): void
    {
        $ws = $this->workspace();

        // No keys → everything off.
        $this->assertFalse($this->caps->interviewsEnabled($ws));
        $this->assertFalse($this->caps->cvAnalysisEnabled($ws));
        $this->assertFalse($this->caps->videoEnabled($ws));

        // OpenAI key → text/voice interviews + CV analysis on; video still off.
        $this->settings->setKey($ws, 'openai', 'sk-openai');
        $this->assertTrue($this->caps->interviewsEnabled($ws));
        $this->assertTrue($this->caps->cvAnalysisEnabled($ws));
        $this->assertFalse($this->caps->videoEnabled($ws));

        // + HeyGen key → video on.
        $this->settings->setKey($ws, 'heygen', 'hg-key');
        $this->assertTrue($this->caps->videoEnabled($ws));

        $status = $this->caps->status($ws);
        $this->assertSame(
            ['openai' => true, 'heygen' => true, 'interviews' => true, 'cv_analysis' => true, 'video' => true],
            $status,
        );
    }

    public function test_keys_and_capabilities_are_isolated_per_workspace(): void
    {
        $wsA = $this->workspace();
        $wsB = $this->workspace();
        $this->settings->setKey($wsA, 'openai', 'sk-A');

        $this->assertTrue($this->caps->interviewsEnabled($wsA));
        // Workspace B never had a key — A's key must not enable B.
        $this->assertFalse($this->caps->interviewsEnabled($wsB));
        $this->assertFalse($this->settings->hasKey($wsB, 'openai'));
    }

    private function workspace(): string
    {
        $owner = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement('INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$owner, 'U', 'o' . substr($owner, -6) . '@x.co', 'x', $now, $now]);
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
