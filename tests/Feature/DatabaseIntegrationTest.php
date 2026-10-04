<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Repository;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real database layer against a live MySQL 8 test database.
 * Skips automatically if the database is unreachable.
 */
final class DatabaseIntegrationTest extends TestCase
{
    private Connection $connection;
    private SchemaBuilder $schema;

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

        $this->schema = new SchemaBuilder($this->connection);
        $this->dropAll();
    }

    protected function tearDown(): void
    {
        $this->dropAll();
    }

    private function dropAll(): void
    {
        foreach (['it_tenant_rows', 'it_items', 'it_migrated', 'migrations'] as $t) {
            $this->schema->dropIfExists($t);
        }
    }

    public function test_schema_builder_creates_tables_and_columns(): void
    {
        $this->schema->create('it_items', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique('name');
        });

        $this->assertTrue($this->schema->hasTable('it_items'));
        $this->assertTrue($this->schema->hasColumn('it_items', 'is_active'));
        $this->assertFalse($this->schema->hasColumn('it_items', 'nope'));
    }

    public function test_repository_crud_with_ulid_keys(): void
    {
        $this->schema->create('it_items', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->string('name');
            $t->timestamps();
        });

        $repo = $this->itemsRepository();

        $id = $repo->create(['name' => 'Alpha']);
        $this->assertSame(26, strlen($id));

        $row = $repo->find($id);
        $this->assertSame('Alpha', $row['name']);
        $this->assertNotNull($row['created_at']);

        $repo->update($id, ['name' => 'Beta']);
        $this->assertSame('Beta', $repo->find($id)['name']);

        $this->assertSame('Beta', $repo->firstWhere(['name' => 'Beta'])['name']);

        $repo->delete($id);
        $this->assertNull($repo->find($id));
    }

    public function test_tenant_guard_isolates_workspaces(): void
    {
        $this->schema->create('it_tenant_rows', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('label');
            $t->timestamps();
            $t->index('workspace_id');
        });

        $repo = $this->tenantRepository();

        $aId = $repo->forWorkspace('WORKSPACE_AAAAAAAAAAAAAAAA')->create(['label' => 'a-row']);
        $repo->forWorkspace('WORKSPACE_BBBBBBBBBBBBBBBB')->create(['label' => 'b-row']);

        // Workspace A sees only its own row.
        $this->assertCount(1, $repo->forWorkspace('WORKSPACE_AAAAAAAAAAAAAAAA')->all());
        $this->assertSame('a-row', $repo->forWorkspace('WORKSPACE_AAAAAAAAAAAAAAAA')->all()[0]['label']);

        // Workspace B cannot read workspace A's row by id (cross-tenant isolation).
        $this->assertNull($repo->forWorkspace('WORKSPACE_BBBBBBBBBBBBBBBB')->find($aId));
        $this->assertNotNull($repo->forWorkspace('WORKSPACE_AAAAAAAAAAAAAAAA')->find($aId));
    }

    public function test_unbound_workspace_scoped_repository_throws(): void
    {
        $this->schema->create('it_tenant_rows', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('label');
            $t->timestamps();
        });

        $this->expectException(\RuntimeException::class);
        $this->tenantRepository()->all(); // no workspace bound
    }

    public function test_migration_runner_runs_and_rolls_back(): void
    {
        $dir = sys_get_temp_dir() . '/mig_' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/2026_01_01_000001_create_it_migrated.php', <<<'PHP'
            <?php
            use HaHireAI\Core\Database\Migrations\Migration;
            use HaHireAI\Core\Database\Schema\Blueprint;
            use HaHireAI\Core\Database\Schema\SchemaBuilder;
            return new class extends Migration {
                public function up(SchemaBuilder $s): void {
                    $s->create('it_migrated', function (Blueprint $t) { $t->ulidPrimary(); $t->string('name'); $t->timestamps(); });
                }
                public function down(SchemaBuilder $s): void { $s->dropIfExists('it_migrated'); }
            };
            PHP);

        $runner = new MigrationRunner($this->connection, $this->schema);

        $applied = $runner->run($dir);
        $this->assertContains('2026_01_01_000001_create_it_migrated', $applied);
        $this->assertTrue($this->schema->hasTable('it_migrated'));

        // Idempotent: running again applies nothing.
        $this->assertSame([], $runner->run($dir));

        $rolledBack = $runner->rollback($dir);
        $this->assertContains('2026_01_01_000001_create_it_migrated', $rolledBack);
        $this->assertFalse($this->schema->hasTable('it_migrated'));

        unlink($dir . '/2026_01_01_000001_create_it_migrated.php');
        rmdir($dir);
    }

    private function itemsRepository(): Repository
    {
        return new class ($this->connection) extends Repository {
            protected function table(): string
            {
                return 'it_items';
            }
        };
    }

    private function tenantRepository(): Repository
    {
        return new class ($this->connection) extends Repository {
            protected function table(): string
            {
                return 'it_tenant_rows';
            }

            protected function workspaceScoped(): bool
            {
                return true;
            }
        };
    }
}
