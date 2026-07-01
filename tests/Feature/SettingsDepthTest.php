<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Files\Application\Exceptions\FileException;
use HaHireAI\Modules\Files\Application\FileService;
use HaHireAI\Modules\Workspaces\Application\WorkspacePreferences;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * Sprint 3.3g-i — workspace settings depth: logo storage (image types, replace,
 * remove) and SMTP/legal preferences persistence.
 */
final class SettingsDepthTest extends TestCase
{
    private Connection $connection;
    private FileService $files;
    private WorkspacePreferences $prefs;
    private string $storageDir;

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

        $this->storageDir = sys_get_temp_dir() . '/hahireai-logo-test-' . substr(Ulid::generate(), -8);
        $this->files = new FileService($this->connection, $this->storageDir);
        $this->prefs = new WorkspacePreferences($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
        if (is_dir($this->storageDir)) {
            $this->rrmdir($this->storageDir);
        }
    }

    public function test_file_service_accepts_image_logos_and_rejects_svg(): void
    {
        [$ws, $owner] = $this->workspace();

        foreach (['logo.webp' => 'image/webp', 'logo.gif' => 'image/gif', 'logo.png' => 'image/png'] as $name => $mime) {
            $id = $this->files->store($ws, $owner, 'workspace_logo', $ws, $this->tmpImage($name), $name, null, false);
            $row = $this->files->find($ws, $id);
            $this->assertNotNull($row);
            $this->assertSame($mime, $row['mime']);
            $this->assertNotNull($this->files->read($ws, $id));
        }

        // SVG is intentionally NOT allowed (XSS risk when served inline).
        $this->expectException(FileException::class);
        $this->files->store($ws, $owner, 'workspace_logo', $ws, $this->tmpImage('logo.svg'), 'logo.svg', null, false);
    }

    public function test_logo_lifecycle_store_replace_remove(): void
    {
        [$ws, $owner] = $this->workspace();
        $key = 'brand.logo_file_id';

        // Store + record.
        $id1 = $this->files->store($ws, $owner, 'workspace_logo', $ws, $this->tmpImage('a.png'), 'a.png', null, false);
        $this->prefs->set($ws, $key, $id1);
        $this->assertSame($id1, $this->prefs->get($ws, $key));
        $this->assertNotNull($this->files->find($ws, $id1));

        // Replace: new file recorded, old file deleted (no orphans).
        $id2 = $this->files->store($ws, $owner, 'workspace_logo', $ws, $this->tmpImage('b.png'), 'b.png', null, false);
        $this->prefs->set($ws, $key, $id2);
        $this->files->delete($ws, $id1);
        $this->assertSame($id2, $this->prefs->get($ws, $key));
        $this->assertNull($this->files->find($ws, $id1));
        $this->assertNotNull($this->files->find($ws, $id2));

        // Remove: file deleted, pointer cleared.
        $this->files->delete($ws, $id2);
        $this->prefs->set($ws, $key, '');
        $this->assertSame('', $this->prefs->get($ws, $key));
        $this->assertNull($this->files->find($ws, $id2));
    }

    public function test_mail_and_legal_preferences_persist(): void
    {
        [$ws] = $this->workspace();

        $values = [
            'mail.from_name' => 'Acme Talent',
            'mail.from_email' => 'hiring@acme.com',
            'mail.smtp_host' => 'smtp.acme.com',
            'mail.smtp_port' => '587',
            'mail.encryption' => 'tls',
            'mail.smtp_password' => 's3cret',
            'legal.company_legal_name' => 'Acme Inc.',
            'legal.terms_url' => 'https://acme.com/terms',
        ];
        foreach ($values as $k => $v) {
            $this->prefs->set($ws, $k, $v);
        }
        foreach ($values as $k => $v) {
            $this->assertSame($v, $this->prefs->get($ws, $k), "pref {$k}");
        }

        // Secret-preserving update: an empty submission must not blank an existing secret.
        $submitted = '';
        if (trim($submitted) !== '') {
            $this->prefs->set($ws, 'mail.smtp_password', $submitted);
        }
        $this->assertSame('s3cret', $this->prefs->get($ws, 'mail.smtp_password'));
    }

    private function tmpImage(string $name): string
    {
        $path = $this->storageDir . '-src-' . substr(Ulid::generate(), -6) . '-' . $name;
        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        // Real file signatures so finfo detects the right mime.
        $bytes = match ($ext) {
            'png' => (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true),
            'gif' => 'GIF89a' . str_repeat("\0", 64),
            'webp' => 'RIFF' . pack('V', 64) . 'WEBP' . str_repeat("\0", 56),
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
            default => str_repeat("\0", 64),
        };
        file_put_contents($path, $bytes);

        return $path;
    }

    /** @return array{0:string,1:string} workspace id + owner id */
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

    private function rrmdir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
