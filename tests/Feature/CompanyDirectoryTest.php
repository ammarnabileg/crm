<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Workspaces\Application\CompanyDirectoryService;
use HaHireAI\Modules\Workspaces\Application\WorkspacePreferences;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** The public company profile resolver behind the careers page (/view/{slug}). */
final class CompanyDirectoryTest extends TestCase
{
    private Connection $connection;
    private CompanyDirectoryService $directory;
    private WorkspacePreferences $prefs;

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
        $this->directory = new CompanyDirectoryService($this->connection, $this->prefs);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_resolves_active_workspace_with_its_company_profile(): void
    {
        $ws = $this->workspace('Acme Talent', 'acme', 'active');
        $this->prefs->set($ws, 'company.industry', 'Software');
        $this->prefs->set($ws, 'company.about', 'We build delightful hiring tools.');
        $this->prefs->set($ws, 'brand.tagline', 'Hiring, reimagined');
        $this->prefs->set($ws, 'brand.color', '#16a34a');
        $this->prefs->set($ws, 'brand.logo_file_id', 'file_123');
        $this->prefs->set($ws, 'legal.company_legal_name', 'Acme Inc.');

        $profile = $this->directory->findBySlug('acme');

        $this->assertNotNull($profile);
        $this->assertSame($ws, $profile['id']);
        $this->assertSame('Acme Talent', $profile['name']);
        $this->assertSame('acme', $profile['slug']);
        $this->assertSame('Software', $profile['industry']);
        $this->assertSame('We build delightful hiring tools.', $profile['about']);
        $this->assertSame('Hiring, reimagined', $profile['tagline']);
        $this->assertSame('#16a34a', $profile['brand_color']);
        $this->assertSame('file_123', $profile['logo_file_id']);
        $this->assertSame('Acme Inc.', $profile['legal_name']);
    }

    public function test_missing_prefs_default_to_empty_and_logo_to_null(): void
    {
        $this->workspace('Bare Co', 'bare', 'active');

        $profile = $this->directory->findBySlug('bare');

        $this->assertNotNull($profile);
        $this->assertSame('', $profile['about']);
        $this->assertSame('', $profile['website']);
        $this->assertNull($profile['logo_file_id']);
    }

    public function test_unknown_slug_returns_null(): void
    {
        $this->assertNull($this->directory->findBySlug('does-not-exist'));
    }

    public function test_inactive_workspaces_are_not_public(): void
    {
        $this->workspace('Archived Co', 'archived-co', 'archived');
        $this->assertNull($this->directory->findBySlug('archived-co'), 'archived workspaces must not expose a careers page');

        $deleted = $this->workspace('Deleted Co', 'deleted-co', 'active');
        $this->connection->statement('UPDATE workspaces SET deleted_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), $deleted]);
        $this->assertNull($this->directory->findBySlug('deleted-co'), 'soft-deleted workspaces must not expose a careers page');
    }

    private function workspace(string $name, string $slug, string $status): string
    {
        $owner = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement('INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$owner, 'U', 'o' . substr($owner, -5) . '@x.co', 'x', $now, $now]);
        $ws = Ulid::generate();
        $this->connection->statement('INSERT INTO workspaces (id, name, slug, owner_user_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$ws, $name, $slug, $owner, $status, $now, $now]);

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
