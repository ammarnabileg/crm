<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Config\Repository;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Core\Http\Request;
use HaHireAI\Modules\Integration\Application\ApiContext;
use HaHireAI\Modules\Integration\Application\ApiTokenService;
use HaHireAI\Modules\Integration\Application\RateLimiter;
use HaHireAI\Modules\Integration\Presentation\Api\ApiController;
use HaHireAI\Modules\Memberships\Application\MembershipService;
use HaHireAI\Modules\Permissions\Application\Authorizer;
use HaHireAI\Modules\Permissions\Application\PermissionSeeder;
use HaHireAI\Modules\Permissions\Application\RoleService;
use HaHireAI\Modules\Permissions\Infrastructure\PermissionRepository;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Modules\Users\Application\PasswordHasher;
use HaHireAI\Modules\Users\Infrastructure\UserRepository;
use HaHireAI\Modules\Workspaces\Application\WorkspaceCreator;
use PHPUnit\Framework\TestCase;

/** Phase 13 — the API Gateway (token auth, RBAC, isolation) on live MySQL 8. */
final class IntegrationApiTest extends TestCase
{
    private Connection $connection;
    private ApiTokenService $tokens;
    private JobService $jobs;
    private ApiController $api;

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
        (new PermissionSeeder(new PermissionRepository($this->connection)))->seed();

        $this->tokens = new ApiTokenService($this->connection);
        $this->jobs = new JobService($this->connection);

        $context = new ApiContext($this->tokens, new MembershipService($this->connection), new Authorizer($this->connection));
        $config = new Repository();
        $config->set('integration.api_rate_limit', 120);
        $config->set('integration.api_rate_window', 60);
        $this->api = new ApiController($context, new RateLimiter($this->connection), $this->jobs, $config);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_me_returns_token_identity_and_permissions(): void
    {
        [$ws, $owner] = $this->workspace('Acme');
        $token = $this->tokens->issue($ws, $owner, 'CI')['plaintext'];

        $response = $this->api->me($this->request('/api/v1/me', $token));
        $this->assertSame(200, $response->status());

        $body = $this->decode($response->content());
        $this->assertSame($ws, $body['data']['workspace_id']);
        $this->assertSame($owner, $body['data']['user_id']);
        $this->assertContains('job.view', $body['data']['permissions']);
        // The gateway annotates rate-limit headers on success.
        $this->assertArrayHasKey('X-RateLimit-Limit', $response->headers());
    }

    public function test_missing_token_is_unauthorized(): void
    {
        $response = $this->api->me($this->request('/api/v1/me', null));
        $this->assertSame(401, $response->status());
        $this->assertSame('unauthorized', $this->decode($response->content())['error']['code']);
    }

    public function test_list_jobs_is_permission_gated_and_workspace_scoped(): void
    {
        [$wsA, $ownerA] = $this->workspace('A');
        [$wsB, $ownerB] = $this->workspace('B', 'b@example.com');

        $this->jobs->create($wsA, $ownerA, 'A-Job-1');
        $this->jobs->create($wsA, $ownerA, 'A-Job-2');
        $this->jobs->create($wsB, $ownerB, 'B-Job-1');

        $tokenA = $this->tokens->issue($wsA, $ownerA, 'A')['plaintext'];
        $response = $this->api->listJobs($this->request('/api/v1/jobs', $tokenA));

        $this->assertSame(200, $response->status());
        $body = $this->decode($response->content());
        $this->assertCount(2, $body['data']);                       // only workspace A's jobs
        $this->assertSame(2, $body['meta']['count']);
        $titles = array_map(static fn (array $j): string => $j['title'], $body['data']);
        $this->assertContains('A-Job-1', $titles);
        $this->assertNotContains('B-Job-1', $titles);
    }

    public function test_member_without_permission_is_forbidden(): void
    {
        [$ws] = $this->workspace('Acme');
        // A bare member: belongs to the workspace but holds no permissions.
        $stranger = $this->user('Stranger', 'stranger@example.com');
        (new MembershipService($this->connection))->create($ws, $stranger);
        $token = $this->tokens->issue($ws, $stranger, 'weak')['plaintext'];

        $response = $this->api->listJobs($this->request('/api/v1/jobs', $token));
        $this->assertSame(403, $response->status());
        $this->assertSame('forbidden', $this->decode($response->content())['error']['code']);
    }

    public function test_revoked_token_is_unauthorized(): void
    {
        [$ws, $owner] = $this->workspace('Acme');
        $issued = $this->tokens->issue($ws, $owner, 'temp');
        $this->tokens->revoke($ws, $issued['id']);

        $response = $this->api->me($this->request('/api/v1/me', $issued['plaintext']));
        $this->assertSame(401, $response->status());
    }

    public function test_show_job_from_another_workspace_is_not_found(): void
    {
        [$wsA, $ownerA] = $this->workspace('A');
        [$wsB, $ownerB] = $this->workspace('B', 'b@example.com');
        $jobB = $this->jobs->create($wsB, $ownerB, 'Secret B role');

        $tokenA = $this->tokens->issue($wsA, $ownerA, 'A')['plaintext'];
        $response = $this->api->showJob($this->request('/api/v1/jobs/' . $jobB, $tokenA), $jobB);

        $this->assertSame(404, $response->status());
    }

    public function test_rate_limiter_blocks_after_limit(): void
    {
        $limiter = new RateLimiter($this->connection);
        $key = 'test:bucket';

        $this->assertTrue($limiter->hit($key, 3)['allowed']);
        $this->assertTrue($limiter->hit($key, 3)['allowed']);
        $this->assertTrue($limiter->hit($key, 3)['allowed']);

        $fourth = $limiter->hit($key, 3);
        $this->assertFalse($fourth['allowed']);
        $this->assertGreaterThan(0, $fourth['retry_after']);
        $this->assertSame(0, $fourth['remaining']);
    }

    private function request(string $path, ?string $token): Request
    {
        $headers = $token !== null ? ['authorization' => 'Bearer ' . $token] : [];

        return new Request('GET', $path, [], [], $headers, []);
    }

    /** @return array<string, mixed> */
    private function decode(string $json): array
    {
        return (array) json_decode($json, true);
    }

    /** @return array{0:string,1:string} [workspaceId, ownerId] */
    private function workspace(string $name, string $ownerEmail = 'owner@example.com'): array
    {
        $ownerId = $this->user('Owner', $ownerEmail);
        $creator = new WorkspaceCreator(
            $this->connection,
            new MembershipService($this->connection),
            new RoleService($this->connection, new PermissionRepository($this->connection)),
        );
        $result = $creator->create($ownerId, $name);

        return [$result['workspace_id'], $ownerId];
    }

    private function user(string $name, string $email): string
    {
        return (new UserRepository($this->connection))->create($name, $email, (new PasswordHasher())->hash('password123'));
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
