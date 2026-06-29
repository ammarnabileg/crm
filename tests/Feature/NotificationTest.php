<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Container\Container;
use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Core\Events\Dispatcher;
use HaHireAI\Modules\Notifications\Application\NotificationService;
use HaHireAI\Modules\Notifications\NotificationsModule;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Personal, per-(workspace,user) notifications on live MySQL 8. */
final class NotificationTest extends TestCase
{
    private Connection $connection;
    private NotificationService $notifications;

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
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_notify_list_and_mark_read(): void
    {
        [$ws, $user] = $this->workspaceUser();

        $this->notifications->notify($ws, $user, 'info', 'First');
        $second = $this->notifications->notify($ws, $user, 'info', 'Second');

        $this->assertCount(2, $this->notifications->forUser($ws, $user));
        $this->assertSame(2, $this->notifications->unreadCount($ws, $user));
        $this->assertSame('Second', $this->notifications->forUser($ws, $user)[0]['title']); // newest first

        $this->notifications->markRead($ws, $user, $second);
        $this->assertSame(1, $this->notifications->unreadCount($ws, $user));

        $this->notifications->markAllRead($ws, $user);
        $this->assertSame(0, $this->notifications->unreadCount($ws, $user));
    }

    public function test_notifications_are_isolated_per_workspace_and_user(): void
    {
        [$wsA, $userX] = $this->workspaceUser();
        [$wsB] = $this->workspaceUser();
        $userY = $this->user('y-' . substr(Ulid::generate(), -6) . '@x.co');

        $this->notifications->notify($wsA, $userX, 'info', 'Only for X in A');

        $this->assertSame(1, $this->notifications->unreadCount($wsA, $userX));
        $this->assertSame(0, $this->notifications->unreadCount($wsB, $userX)); // other workspace
        $this->assertSame(0, $this->notifications->unreadCount($wsA, $userY)); // other user
    }

    public function test_module_listener_notifies_candidate_on_application_submitted(): void
    {
        [$ws, $candidate] = $this->workspaceUser();

        $container = new Container();
        $container->instance(Connection::class, $this->connection);
        $container->instance(EventDispatcher::class, new Dispatcher());
        (new NotificationsModule())->boot($container);

        $events = $container->make(EventDispatcher::class);
        $this->assertTrue($events->hasListeners('application.submitted'));

        $events->dispatch('application.submitted', [
            'workspace_id' => $ws,
            'user_id' => $candidate,
            'job_title' => 'PHP Engineer',
            'job_id' => Ulid::generate(),
        ]);

        $list = $this->notifications->forUser($ws, $candidate);
        $this->assertCount(1, $list);
        $this->assertSame('application', $list[0]['type']);
        $this->assertStringContainsString('PHP Engineer', (string) $list[0]['body']);
    }

    /** @return array{0:string,1:string} [workspaceId, userId] */
    private function workspaceUser(): array
    {
        $userId = $this->user('u-' . substr(Ulid::generate(), -6) . '@x.co');
        $workspaceId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$workspaceId, 'Acme', 'acme-' . substr($workspaceId, -6), $userId, $now, $now],
        );

        return [$workspaceId, $userId];
    }

    private function user(string $email): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$id, 'User', $email, 'x', $now, $now],
        );

        return $id;
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
