<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Container\Container;
use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Core\Events\Dispatcher;
use HaHireAI\Modules\Integration\Application\WebhookDispatcher;
use HaHireAI\Modules\Integration\Application\WebhookService;
use HaHireAI\Modules\Integration\Contracts\HttpClient;
use HaHireAI\Modules\Integration\Domain\HttpClientResponse;
use HaHireAI\Modules\Integration\IntegrationModule;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Phase 13 — outbound webhooks driven by the event bus, on live MySQL 8. */
final class WebhookTest extends TestCase
{
    private Connection $connection;
    private WebhookService $webhooks;

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
        $this->webhooks = new WebhookService($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_event_is_delivered_to_subscribed_endpoint_with_valid_signature(): void
    {
        $ws = $this->workspace();
        $endpoint = $this->webhooks->createEndpoint($ws, 'https://example.test/hook', ['application.submitted']);

        $http = $this->fakeHttp(200);
        $dispatcher = new WebhookDispatcher($this->connection, $this->webhooks, $http);

        $delivered = $dispatcher->dispatch($ws, 'application.submitted', ['application_id' => 'abc', 'candidate_name' => 'Sara']);

        $this->assertSame(1, $delivered);
        $this->assertCount(1, $http->calls);
        $call = $http->calls[0];
        $this->assertSame('https://example.test/hook', $call['url']);
        $this->assertSame('application.submitted', $call['headers']['X-HaHireAI-Event']);

        // The signature is a valid HMAC of the exact body with the endpoint secret.
        $expected = 'sha256=' . hash_hmac('sha256', $call['body'], $endpoint['secret']);
        $this->assertSame($expected, $call['headers']['X-HaHireAI-Signature']);

        $row = $this->connection->selectOne('SELECT * FROM webhook_deliveries WHERE workspace_id = ?', [$ws]);
        $this->assertSame('delivered', $row['status']);
        $this->assertSame(200, (int) $row['response_status']);

        $ep = $this->connection->selectOne('SELECT last_delivered_at, failure_count FROM webhook_endpoints WHERE id = ?', [$endpoint['id']]);
        $this->assertNotNull($ep['last_delivered_at']);
        $this->assertSame(0, (int) $ep['failure_count']);
    }

    public function test_failed_delivery_is_recorded_and_increments_failure_count(): void
    {
        $ws = $this->workspace();
        $endpoint = $this->webhooks->createEndpoint($ws, 'https://example.test/hook', ['application.submitted']);

        $dispatcher = new WebhookDispatcher($this->connection, $this->webhooks, $this->fakeHttp(500));
        $dispatcher->dispatch($ws, 'application.submitted', ['x' => 1]);

        $row = $this->connection->selectOne('SELECT status, response_status FROM webhook_deliveries WHERE endpoint_id = ?', [$endpoint['id']]);
        $this->assertSame('failed', $row['status']);
        $this->assertSame(500, (int) $row['response_status']);

        $ep = $this->connection->selectOne('SELECT failure_count FROM webhook_endpoints WHERE id = ?', [$endpoint['id']]);
        $this->assertSame(1, (int) $ep['failure_count']);
    }

    public function test_only_subscribed_enabled_endpoints_receive_the_event(): void
    {
        $ws = $this->workspace();
        $this->webhooks->createEndpoint($ws, 'https://example.test/other', ['offer.accepted']);     // wrong event
        $disabled = $this->webhooks->createEndpoint($ws, 'https://example.test/off', ['application.submitted']);
        $this->webhooks->setEnabled($ws, $disabled['id'], false);
        $this->webhooks->createEndpoint($ws, 'https://example.test/yes', ['application.submitted']); // the only match

        $http = $this->fakeHttp(200);
        $delivered = (new WebhookDispatcher($this->connection, $this->webhooks, $http))
            ->dispatch($ws, 'application.submitted', ['x' => 1]);

        $this->assertSame(1, $delivered);
        $this->assertSame('https://example.test/yes', $http->calls[0]['url']);
    }

    public function test_deliveries_are_isolated_per_workspace(): void
    {
        $a = $this->workspace();
        $b = $this->workspace();
        $this->webhooks->createEndpoint($a, 'https://example.test/a', ['application.submitted']);

        $http = $this->fakeHttp(200);
        $delivered = (new WebhookDispatcher($this->connection, $this->webhooks, $http))
            ->dispatch($b, 'application.submitted', ['x' => 1]);   // fire in B

        $this->assertSame(0, $delivered);                          // A's endpoint is untouched
        $this->assertCount(0, $http->calls);
    }

    public function test_module_listener_delivers_webhook_on_dispatched_event(): void
    {
        $ws = $this->workspace();
        $this->webhooks->createEndpoint($ws, 'https://example.test/hook', ['application.submitted']);

        $http = $this->fakeHttp(200);
        $container = new Container();
        $container->instance(Connection::class, $this->connection);
        $container->instance(EventDispatcher::class, new Dispatcher());
        $container->instance(HttpClient::class, $http);

        // Register the integration listeners exactly as the Kernel does at boot.
        (new IntegrationModule())->boot($container);

        $events = $container->make(EventDispatcher::class);
        $this->assertTrue($events->hasListeners('application.submitted'));

        $events->dispatch('application.submitted', ['workspace_id' => $ws, 'application_id' => 'abc']);

        $this->assertCount(1, $http->calls);
        $this->assertSame('delivered', $this->connection->selectOne('SELECT status FROM webhook_deliveries WHERE workspace_id = ?', [$ws])['status']);
    }

    /** A fake HttpClient that records calls and returns a canned status. */
    private function fakeHttp(int $status): HttpClient
    {
        return new class ($status) implements HttpClient {
            /** @var list<array{url: string, body: string, headers: array<string,string>}> */
            public array $calls = [];

            public function __construct(private readonly int $status)
            {
            }

            public function post(string $url, string $body, array $headers = [], int $timeoutSeconds = 10): HttpClientResponse
            {
                $this->calls[] = ['url' => $url, 'body' => $body, 'headers' => $headers];

                return new HttpClientResponse($this->status, $this->status < 400 ? 'ok' : 'error');
            }
        };
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
