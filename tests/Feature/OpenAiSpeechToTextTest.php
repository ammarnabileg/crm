<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\AiEngine\Application\AiSettingsService;
use HaHireAI\Modules\AiEngine\Infrastructure\OpenAiSpeechToText;
use HaHireAI\Shared\Encrypter;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * Whisper STT uses the workspace's own OpenAI key, parses the transcript, and
 * degrades to null (no key / failure) — keys never cross workspace boundaries.
 */
final class OpenAiSpeechToTextTest extends TestCase
{
    private Connection $connection;
    private AiSettingsService $settings;
    private string $audio = '';

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
        $this->audio = (string) tempnam(sys_get_temp_dir(), 'sttaudio');
        file_put_contents($this->audio, 'FAKEAUDIOBYTES');
    }

    protected function tearDown(): void
    {
        @unlink($this->audio);
        $this->wipe();
    }

    public function test_uses_workspace_key_and_parses_transcript(): void
    {
        $ws = $this->workspace();
        $this->settings->setKey($ws, 'openai', 'sk-workspace-secret');

        $seenKey = null;
        $stt = new OpenAiSpeechToText($this->settings, function (string $apiKey) use (&$seenKey): ?string {
            $seenKey = $apiKey;

            return '{"text": "  Hello from Whisper.  "}';
        });

        $text = $stt->transcribe($ws, $this->audio, 'answer.webm', 'audio/webm');
        $this->assertSame('Hello from Whisper.', $text);
        $this->assertSame('sk-workspace-secret', $seenKey, 'must call OpenAI with the workspace key');
    }

    public function test_returns_null_without_a_key(): void
    {
        $ws = $this->workspace();
        $called = false;
        $stt = new OpenAiSpeechToText($this->settings, function () use (&$called): ?string {
            $called = true;

            return '{"text":"nope"}';
        });

        $this->assertNull($stt->transcribe($ws, $this->audio, 'a.webm'));
        $this->assertFalse($called, 'no transport call when the workspace has no key');
    }

    public function test_key_isolation_between_workspaces(): void
    {
        $wsA = $this->workspace();
        $wsB = $this->workspace();
        $this->settings->setKey($wsA, 'openai', 'sk-A');

        $stt = new OpenAiSpeechToText($this->settings, fn (): ?string => '{"text":"x"}');

        $this->assertNotNull($stt->transcribe($wsA, $this->audio, 'a.webm'));
        // Workspace B never had a key set — A's key must not leak across.
        $this->assertNull($stt->transcribe($wsB, $this->audio, 'a.webm'));
    }

    public function test_returns_null_on_transport_failure_or_bad_json(): void
    {
        $ws = $this->workspace();
        $this->settings->setKey($ws, 'openai', 'sk-x');

        $this->assertNull((new OpenAiSpeechToText($this->settings, fn (): ?string => null))->transcribe($ws, $this->audio, 'a.webm'));
        $this->assertNull((new OpenAiSpeechToText($this->settings, fn (): ?string => 'not-json'))->transcribe($ws, $this->audio, 'a.webm'));
        $this->assertNull((new OpenAiSpeechToText($this->settings, fn (): ?string => '{"text":""}'))->transcribe($ws, $this->audio, 'a.webm'));
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
