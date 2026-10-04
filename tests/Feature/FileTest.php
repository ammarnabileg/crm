<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Files\Application\Exceptions\FileException;
use HaHireAI\Modules\Files\Application\FileService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Files & CVs — workspace-scoped attachments on live MySQL 8. */
final class FileTest extends TestCase
{
    private Connection $connection;
    private FileService $files;
    private string $storageDir;
    private string $tmpSource;

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

        $this->storageDir = sys_get_temp_dir() . '/hahireai-files-' . substr(Ulid::generate(), -8);
        $this->files = new FileService($this->connection, $this->storageDir);

        $this->tmpSource = (string) tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($this->tmpSource, "%PDF-1.4 fake resume contents\n");
    }

    protected function tearDown(): void
    {
        $this->wipe();
        @unlink($this->tmpSource);
        foreach (glob($this->storageDir . '/*/*') ?: [] as $f) {
            @unlink($f);
        }
    }

    public function test_store_lists_and_reads_a_file(): void
    {
        $ws = $this->workspace();
        $profileId = Ulid::generate();

        $id = $this->files->store($ws, null, 'candidate_profile', $profileId, $this->tmpSource, 'resume.pdf', null, false);

        $list = $this->files->listForEntity($ws, 'candidate_profile', $profileId);
        $this->assertCount(1, $list);
        $this->assertSame('resume.pdf', $list[0]['original_name']);
        $this->assertGreaterThan(0, (int) $list[0]['size_bytes']);

        $bytes = $this->files->read($ws, $id);
        $this->assertNotNull($bytes);
        $this->assertStringContainsString('fake resume', (string) $bytes);

        $row = $this->files->find($ws, $id);
        $this->assertFileExists((string) $row['stored_path']);     // stored outside the web root
    }

    public function test_rejects_disallowed_extension(): void
    {
        $ws = $this->workspace();
        $this->expectException(FileException::class);
        $this->files->store($ws, null, null, null, $this->tmpSource, 'malware.exe', null, false);
    }

    public function test_rejects_oversize_file(): void
    {
        $ws = $this->workspace();
        $this->expectException(FileException::class);
        // Declare a size beyond the 10 MB cap (no need to write a huge file).
        $this->files->store($ws, null, null, null, $this->tmpSource, 'huge.pdf', 11 * 1024 * 1024, false);
    }

    public function test_files_are_isolated_per_workspace(): void
    {
        $a = $this->workspace();
        $b = $this->workspace();
        $profileId = Ulid::generate();
        $id = $this->files->store($a, null, 'candidate_profile', $profileId, $this->tmpSource, 'resume.pdf', null, false);

        $this->assertNull($this->files->find($b, $id));                       // B cannot see A's file
        $this->assertNull($this->files->read($b, $id));                       // …nor read its bytes
        $this->assertCount(0, $this->files->listForEntity($b, 'candidate_profile', $profileId));
    }

    public function test_delete_removes_row_and_disk_file(): void
    {
        $ws = $this->workspace();
        $id = $this->files->store($ws, null, null, null, $this->tmpSource, 'resume.pdf', null, false);
        $path = (string) $this->files->find($ws, $id)['stored_path'];

        $this->files->delete($ws, $id);

        $this->assertNull($this->files->find($ws, $id));
        $this->assertFileDoesNotExist($path);
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
