<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Non-AI ATS keyword search matches CV-derived data (name/email/summary/details). */
final class CandidateKeywordSearchTest extends TestCase
{
    private Connection $connection;
    private CandidateProfileService $profiles;
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
        $this->profiles = new CandidateProfileService($this->connection);
        $this->ws = $this->workspace();
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_keyword_matches_name_email_and_cv_details(): void
    {
        $sara = $this->candidate('Sara Hassan', 'sara@acme.com');
        $this->profiles->getOrCreate($this->ws, $sara);
        $this->profiles->saveDetails($this->ws, $sara, ['skills' => ['PHP', 'Laravel'], 'education' => 'BSc Computer Science']);

        $omar = $this->candidate('Omar Ali', 'omar@acme.com');
        $this->profiles->getOrCreate($this->ws, $omar);
        $this->profiles->saveDetails($this->ws, $omar, ['skills' => ['React', 'Figma']]);

        // by name
        $this->assertSame([$sara], $this->ids($this->profiles->searchByKeyword($this->ws, 'sara')));
        // by skill stored in CV details
        $this->assertSame([$sara], $this->ids($this->profiles->searchByKeyword($this->ws, 'Laravel')));
        $this->assertSame([$omar], $this->ids($this->profiles->searchByKeyword($this->ws, 'Figma')));
        // by email
        $this->assertSame([$omar], $this->ids($this->profiles->searchByKeyword($this->ws, 'omar@acme')));
        // no match
        $this->assertSame([], $this->profiles->searchByKeyword($this->ws, 'Kubernetes'));
    }

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private function ids(array $rows): array
    {
        return array_map(static fn (array $r): string => (string) $r['user_id'], $rows);
    }

    private function candidate(string $name, string $email): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement('INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$id, $name, $email, 'x', $now, $now]);

        return $id;
    }

    private function workspace(): string
    {
        $owner = $this->candidate('Owner', 'owner' . substr(Ulid::generate(), -5) . '@x.co');
        $ws = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
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
