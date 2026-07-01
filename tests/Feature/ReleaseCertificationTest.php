<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Container\Container;
use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Core\Events\Dispatcher;
use HaHireAI\Modules\AiEngine\Application\AiSettingsService;
use HaHireAI\Modules\AiEngine\Application\PromptEngine;
use HaHireAI\Modules\AiEngine\Application\ProviderRegistry;
use HaHireAI\Modules\AiEngine\Infrastructure\Providers\EchoProvider;
use HaHireAI\Modules\Billing\Application\BillingService;
use HaHireAI\Modules\Billing\Application\InvoiceService;
use HaHireAI\Modules\Billing\Application\PlanService;
use HaHireAI\Modules\Billing\Application\SubscriptionService;
use HaHireAI\Modules\Billing\Infrastructure\ManualPaymentGateway;
use HaHireAI\Modules\Integration\Application\WebhookService;
use HaHireAI\Modules\Integration\Contracts\HttpClient;
use HaHireAI\Modules\Integration\Domain\HttpClientResponse;
use HaHireAI\Modules\Integration\IntegrationModule;
use HaHireAI\Modules\Observability\Application\ErrorTracker;
use HaHireAI\Modules\Observability\Application\MetricsService;
use HaHireAI\Modules\Observability\ObservabilityModule;
use HaHireAI\Modules\Permissions\Application\PermissionSeeder;
use HaHireAI\Modules\Permissions\Application\RoleService;
use HaHireAI\Modules\Permissions\Infrastructure\PermissionRepository;
use HaHireAI\Modules\Recruitment\Application\ApplicationService;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Modules\Users\Application\PasswordHasher;
use HaHireAI\Modules\Users\Infrastructure\UserRepository;
use HaHireAI\Modules\Workflow\Application\WorkflowService;
use HaHireAI\Modules\Workflow\WorkflowModule;
use HaHireAI\Modules\Workspaces\Application\WorkspaceCreator;
use HaHireAI\Shared\Encrypter;
use HaHireAI\Modules\Memberships\Application\MembershipService;
use PHPUnit\Framework\TestCase;

/**
 * Phase 16 — the whole-platform certification. One realistic flow proves every
 * module integrates through the real container + event bus: a candidate applies
 * to a job and the single domain event fans out to the Workflow Engine (AI) AND
 * the Webhook dispatcher, on a billed workspace, with everything observable.
 */
final class ReleaseCertificationTest extends TestCase
{
    private Connection $connection;
    private Container $container;

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

        // A container wired like the booted kernel: real bus, echo AI, fake HTTP.
        $this->container = new Container();
        $this->container->instance(Connection::class, $this->connection);
        $this->container->instance(EventDispatcher::class, new Dispatcher());
        $this->container->instance(\HaHireAI\Core\Contracts\AuditRecorder::class, new \HaHireAI\Modules\Audit\Application\AuditLogger($this->connection));
        $this->container->instance(HttpClient::class, $this->fakeHttp());
        // Workflow action write-surfaces — Null defaults, as CoreServiceProvider binds at boot.
        $this->container->instance(\HaHireAI\Core\Contracts\TaskWriter::class, new \HaHireAI\Core\Workflow\NullTaskWriter());
        $this->container->instance(\HaHireAI\Core\Contracts\NotificationWriter::class, new \HaHireAI\Core\Workflow\NullNotificationWriter());
        $this->container->instance(\HaHireAI\Core\Contracts\RecruitmentActions::class, new \HaHireAI\Core\Workflow\NullRecruitmentActions());
        $this->container->instance(\HaHireAI\Core\Contracts\LearningCatalog::class, new \HaHireAI\Core\Learning\NullLearningCatalog());

        $registry = new ProviderRegistry();
        $registry->register(new EchoProvider());
        $prompts = new PromptEngine($this->connection);
        $prompts->seedDefaults();
        $this->container->instance(ProviderRegistry::class, $registry);
        $this->container->instance(PromptEngine::class, $prompts);
        $this->container->instance(AiSettingsService::class, new AiSettingsService(
            $this->connection,
            new Encrypter('base64:' . base64_encode(str_repeat('k', 32))),
        ));

        // Register reactors exactly as the kernel does at boot.
        (new WorkflowModule())->boot($this->container);
        (new IntegrationModule())->boot($this->container);
        (new ObservabilityModule())->boot($this->container);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_full_platform_flow_end_to_end(): void
    {
        $events = $this->container->make(EventDispatcher::class);

        // 1) A workspace owner (one User, direct-granted permissions — no reserved role).
        $ownerId = (new UserRepository($this->connection))->create('Ammar', 'ammar@watad.test', (new PasswordHasher())->hash('password123'));
        $creator = new WorkspaceCreator(
            $this->connection,
            new MembershipService($this->connection),
            new RoleService($this->connection, new PermissionRepository($this->connection)),
        );
        $ws = $creator->create($ownerId, 'Watad')['workspace_id'];

        // 2) Billing: the workspace subscribes (Pro → trial).
        $plans = new PlanService($this->connection);
        $plans->seedDefaults();
        $billing = new BillingService(new SubscriptionService($this->connection), $plans, new InvoiceService($this->connection), new ManualPaymentGateway());
        $billing->subscribe($ws, (string) $plans->findByCode('pro')['id']);

        // 3) Automation + Integration subscribe to the SAME domain event.
        (new WorkflowService($this->connection))->create($ws, 'AI screen', 'application.submitted', [
            ['action' => 'audit', 'params' => ['message' => 'received']],
            ['action' => 'run_ai', 'params' => ['capability' => 'summarize_candidate']],
        ]);
        (new WebhookService($this->connection))->createEndpoint($ws, 'https://watad.test/hook', ['application.submitted']);

        // 4) Recruitment: publish a job; a (different User) candidate applies.
        $jobs = new JobService($this->connection);
        $candidates = new CandidateProfileService($this->connection);
        $applications = new ApplicationService($this->connection, $jobs, $candidates);
        $jobId = $jobs->create($ws, $ownerId, 'Senior PHP Engineer');
        $jobs->publish($ws, $jobId);
        $candidateId = (new UserRepository($this->connection))->create('Sara', 'sara@candidate.test', (new PasswordHasher())->hash('password123'));
        $appId = $applications->apply($ws, $jobId, $candidateId, 'Excited to apply');

        // 5) The single event fans out to BOTH reactors (as PublicJobController does).
        $events->dispatch('application.submitted', [
            'workspace_id' => $ws,
            'application_id' => $appId,
            'job_id' => $jobId,
            'user_id' => $candidateId,
            'candidate_name' => 'Sara',
        ]);

        // ── Assert the fan-out landed everywhere ────────────────────────────
        $execution = $this->connection->selectOne('SELECT * FROM workflow_executions WHERE workspace_id = ?', [$ws]);
        $this->assertSame('completed', $execution['status'], 'workflow ran');

        $aiSession = $this->connection->selectOne('SELECT capability FROM ai_sessions WHERE workspace_id = ?', [$ws]);
        $this->assertSame('summarize_candidate', $aiSession['capability'], 'AI engine invoked via workflow');

        $delivery = $this->connection->selectOne('SELECT status FROM webhook_deliveries WHERE workspace_id = ?', [$ws]);
        $this->assertSame('delivered', $delivery['status'], 'webhook delivered');

        // 6) Billing state is correct (trialing, no charge yet).
        $sub = (new SubscriptionService($this->connection))->find($ws);
        $this->assertSame('trialing', $sub['status']);

        // 7) Observability sees the whole platform.
        $metrics = (new MetricsService($this->connection))->platformSnapshot();
        $this->assertSame(1, $metrics['tenants']['workspaces']);
        $this->assertSame(1, $metrics['recruitment']['published_jobs']);
        $this->assertGreaterThanOrEqual(1, $metrics['recruitment']['applications']);
        $this->assertGreaterThanOrEqual(1, $metrics['ai']['sessions']);
        $this->assertSame(1, $metrics['subscriptions']['trialing']);

        // 8) Errors are captured via the bus.
        $events->dispatch('system.error', ['message' => 'synthetic', 'exception_class' => 'RuntimeException']);
        $this->assertSame(1, (new ErrorTracker($this->connection))->countSince(time() - 60));
    }

    private function fakeHttp(): HttpClient
    {
        return new class implements HttpClient {
            public function post(string $url, string $body, array $headers = [], int $timeoutSeconds = 10): HttpClientResponse
            {
                return new HttpClientResponse(200, 'ok');
            }
        };
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
