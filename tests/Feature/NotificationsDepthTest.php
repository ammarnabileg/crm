<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Notifications\Application\NotificationService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Sprint 4c — notifications gain categories, search and archive. */
final class NotificationsDepthTest extends TestCase
{
    private Connection $connection;
    private NotificationService $notifications;
    private string $ws = '';
    private string $user = '';

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
        $this->notifications = new NotificationService($this->connection);
        [$this->ws, $this->user] = $this->seed();
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_state_category_and_search_filters(): void
    {
        $this->notifications->notify($this->ws, $this->user, 'offer', 'You have an offer', 'Senior role');
        $this->notifications->notify($this->ws, $this->user, 'interview', 'Interview scheduled', 'Tuesday 3pm');
        $read = $this->notifications->notify($this->ws, $this->user, 'application', 'Application received');
        $this->notifications->markRead($this->ws, $this->user, $read);

        $this->assertCount(3, $this->notifications->forUser($this->ws, $this->user, ['state' => 'all']));
        $this->assertCount(2, $this->notifications->forUser($this->ws, $this->user, ['state' => 'unread']));

        // Category filter (uses the type column).
        $offers = $this->notifications->forUser($this->ws, $this->user, ['category' => 'offer']);
        $this->assertCount(1, $offers);
        $this->assertSame('You have an offer', $offers[0]['title']);

        // Search across title + body.
        $this->assertCount(1, $this->notifications->forUser($this->ws, $this->user, ['q' => 'Tuesday']));

        $this->assertEqualsCanonicalizing(
            ['application', 'interview', 'offer'],
            $this->notifications->categories($this->ws, $this->user),
        );
    }

    public function test_archive_unarchive_and_counts(): void
    {
        $a = $this->notifications->notify($this->ws, $this->user, 'info', 'First');
        $this->notifications->notify($this->ws, $this->user, 'info', 'Second');

        $this->assertSame(['all' => 2, 'unread' => 2, 'archived' => 0], $this->notifications->counts($this->ws, $this->user));
        $this->assertSame(2, $this->notifications->unreadCount($this->ws, $this->user));

        // Archiving removes it from active + unread, moves it to archived.
        $this->notifications->archive($this->ws, $this->user, $a);
        $this->assertSame(['all' => 1, 'unread' => 1, 'archived' => 1], $this->notifications->counts($this->ws, $this->user));
        $this->assertSame(1, $this->notifications->unreadCount($this->ws, $this->user));
        $this->assertCount(1, $this->notifications->forUser($this->ws, $this->user, ['state' => 'archived']));
        $this->assertCount(1, $this->notifications->forUser($this->ws, $this->user, ['state' => 'all']));

        // Restoring brings it back to active.
        $this->notifications->unarchive($this->ws, $this->user, $a);
        $this->assertSame(['all' => 2, 'unread' => 2, 'archived' => 0], $this->notifications->counts($this->ws, $this->user));
    }

    /** @return array{0:string,1:string} */
    private function seed(): array
    {
        $user = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement('INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$user, 'U', 'u' . substr($user, -5) . '@x.co', 'x', $now, $now]);
        $ws = Ulid::generate();
        $this->connection->statement('INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$ws, 'Acme', 'acme-' . substr($ws, -6), $user, $now, $now]);

        return [$ws, $user];
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
