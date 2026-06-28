<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Sprint 4a — careers page search, filters and facets over published jobs. */
final class CareersSearchTest extends TestCase
{
    private Connection $connection;
    private JobService $jobs;
    private string $ws = '';
    private string $owner = '';

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
        $this->jobs = new JobService($this->connection);
        [$this->ws, $this->owner] = $this->workspace();

        $this->publishJob('Senior PHP Engineer', 'full-time', 'Senior', 'Cairo', 'Build the backend platform.');
        $this->publishJob('Junior Frontend Developer', 'full-time', 'Junior', 'Remote', 'React and Tailwind.');
        $this->publishJob('Product Designer', 'contract', 'Mid', 'Remote', 'Design delightful flows.');
        $this->draftJob('Hidden Draft Role', 'full-time', 'Senior', 'Cairo');
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_lists_only_published_and_supports_text_search(): void
    {
        $all = $this->jobs->listPublished($this->ws, $this->owner);
        $this->assertCount(3, $all); // draft excluded

        $byTitle = $this->jobs->listPublished($this->ws, $this->owner, ['q' => 'engineer']);
        $this->assertCount(1, $byTitle);
        $this->assertSame('Senior PHP Engineer', $byTitle[0]['title']);

        $byKeyword = $this->jobs->listPublished($this->ws, $this->owner, ['q' => 'Tailwind']);
        $this->assertCount(1, $byKeyword);

        $byLocation = $this->jobs->listPublished($this->ws, $this->owner, ['q' => 'remote']);
        $this->assertCount(2, $byLocation);
    }

    public function test_structured_filters_and_facets(): void
    {
        $contract = $this->jobs->listPublished($this->ws, $this->owner, ['employment_type' => 'contract']);
        $this->assertCount(1, $contract);
        $this->assertSame('Product Designer', $contract[0]['title']);

        $seniorRemote = $this->jobs->listPublished($this->ws, $this->owner, ['seniority' => 'Junior', 'location' => 'Remote']);
        $this->assertCount(1, $seniorRemote);

        $facets = $this->jobs->publishedFacets($this->ws);
        $this->assertEqualsCanonicalizing(['contract', 'full-time'], $facets['employment_type']);
        $this->assertEqualsCanonicalizing(['Junior', 'Mid', 'Senior'], $facets['seniority']);
        $this->assertEqualsCanonicalizing(['Cairo', 'Remote'], $facets['location']);
    }

    private function publishJob(string $title, string $type, string $seniority, string $location, string $desc): string
    {
        return $this->insertJob($title, $type, $seniority, $location, $desc, 'published');
    }

    private function draftJob(string $title, string $type, string $seniority, string $location): string
    {
        return $this->insertJob($title, $type, $seniority, $location, '', 'draft');
    }

    private function insertJob(string $title, string $type, string $seniority, string $location, string $desc, string $status): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO jobs (id, workspace_id, title, seniority, currency, slug, description, status, employment_type, location, public_token, created_by, published_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $this->ws, $title, $seniority, 'USD', 'slug-' . substr($id, -6), $desc, $status, $type, $location, substr($id, -16), $this->owner, $status === 'published' ? $now : null, $now, $now],
        );

        return $id;
    }

    /** @return array{0:string,1:string} */
    private function workspace(): array
    {
        $owner = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement('INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$owner, 'U', 'o' . substr($owner, -5) . '@x.co', 'x', $now, $now]);
        $ws = Ulid::generate();
        $this->connection->statement('INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$ws, 'Acme', 'acme-' . substr($ws, -6), $owner, $now, $now]);

        return [$ws, $owner];
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
