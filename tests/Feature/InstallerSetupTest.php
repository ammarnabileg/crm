<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Core\Events\Dispatcher;
use HaHireAI\Modules\Installer\Application\Exceptions\InstallerException;
use HaHireAI\Modules\Installer\Application\Installer;
use HaHireAI\Modules\Permissions\Application\PermissionSeeder;
use HaHireAI\Modules\Permissions\Infrastructure\PermissionRepository;
use HaHireAI\Modules\Users\Application\PasswordHasher;
use HaHireAI\Modules\Users\Application\UserRegistrar;
use HaHireAI\Modules\Users\Infrastructure\UserRepository;
use PHPUnit\Framework\TestCase;

/** The browser installer: DB credential capture, .env writing, and the safe console. */
final class InstallerSetupTest extends TestCase
{
    private Connection $connection;
    /** @var array{host:string,port:int,database:string,username:string,password:string} */
    private array $db;
    private string $tmp = '';

    protected function setUp(): void
    {
        $this->db = [
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_DATABASE') ?: 'hahireai_test',
            'username' => getenv('DB_USERNAME') ?: 'hahireai',
            'password' => getenv('DB_PASSWORD') ?: 'hahireai_pw',
        ];
        $this->connection = new Connection($this->db + ['charset' => 'utf8mb4']);
        try {
            $this->connection->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL test database unavailable: ' . $e->getMessage());
        }
        $this->tmp = sys_get_temp_dir() . '/hahireai_setup_' . bin2hex(random_bytes(4));
        @mkdir($this->tmp, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmp . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmp);
    }

    public function test_database_test_accepts_good_creds_and_rejects_bad(): void
    {
        $this->installer()->testDatabase($this->db); // no throw

        $this->expectException(InstallerException::class);
        $this->installer()->testDatabase(['host' => '127.0.0.1', 'port' => 3306, 'database' => 'nope_x', 'username' => 'nobody', 'password' => 'bad']);
    }

    public function test_writes_database_credentials_to_env(): void
    {
        $env = $this->tmp . '/.env';
        file_put_contents($env, "APP_ENV=production\nDB_HOST=old\n");
        $this->installer($env)->writeDatabaseConfig($this->db);

        $written = (string) file_get_contents($env);
        $this->assertStringContainsString('DB_HOST=' . $this->db['host'], $written);
        $this->assertStringContainsString('DB_DATABASE=' . $this->db['database'], $written);
        $this->assertStringContainsString('APP_ENV=production', $written); // preserved
        $this->assertSame(1, substr_count($written, 'DB_HOST='), 'no duplicate keys');
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:.+/m', $written, 'generates an app key');
    }

    public function test_does_not_overwrite_an_existing_app_key(): void
    {
        $env = $this->tmp . '/.env';
        file_put_contents($env, "APP_KEY=base64:KEEPTHISKEY\n");
        $this->installer($env)->writeDatabaseConfig($this->db);

        $written = (string) file_get_contents($env);
        $this->assertStringContainsString('APP_KEY=base64:KEEPTHISKEY', $written);
        $this->assertSame(1, substr_count($written, 'APP_KEY='), 'app key not duplicated');
    }

    public function test_console_is_allow_listed_only(): void
    {
        $installer = $this->installer();
        $this->assertArrayHasKey('migrate', $installer->consoleCommands());

        $out = $installer->runConsole('requirements');
        $this->assertStringContainsString('PHP', $out);

        $this->expectException(InstallerException::class);
        $installer->runConsole('rm -rf /'); // arbitrary commands are rejected
    }

    private function installer(string $envPath = ''): Installer
    {
        return new Installer(
            new MigrationRunner($this->connection, new SchemaBuilder($this->connection)),
            new PermissionSeeder(new PermissionRepository($this->connection)),
            new UserRegistrar(new UserRepository($this->connection), new PasswordHasher()),
            new Dispatcher(),
            $this->tmp . '/installed.lock',
            dirname(__DIR__, 2) . '/database/migrations',
            $envPath,
        );
    }
}
