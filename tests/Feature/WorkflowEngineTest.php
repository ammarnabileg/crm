<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Container\Container;
use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Core\Events\Dispatcher;
use HaHireAI\Modules\AiEngine\Application\AiEngine;
use HaHireAI\Modules\AiEngine\Application\AiSettingsService;
use HaHireAI\Modules\AiEngine\Application\PromptEngine;
use HaHireAI\Modules\AiEngine\Application\ProviderRegistry;
use HaHireAI\Modules\AiEngine\Infrastructure\Providers\EchoProvider;
use HaHireAI\Core\Workflow\NullNotificationWriter;
use HaHireAI\Core\Workflow\NullRecruitmentActions;
use HaHireAI\Core\Workflow\NullTaskWriter;
use HaHireAI\Modules\Audit\Application\AuditLogger;
use HaHireAI\Modules\Notifications\Application\NotificationService;
use HaHireAI\Modules\Notifications\Application\NotificationWriterAdapter;
use HaHireAI\Modules\Tasks\Application\TaskService;
use HaHireAI\Modules\Tasks\Application\TaskWriterAdapter;
use HaHireAI\Modules\Workflow\Application\ActionExecutor;
use HaHireAI\Modules\Workflow\Application\WorkflowCollectionService;
use HaHireAI\Modules\Workflow\Application\WorkflowEngine;
use HaHireAI\Modules\Workflow\Application\WorkflowService;
use HaHireAI\Modules\Workflow\WorkflowModule;
use HaHireAI\Shared\Encrypter;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Phase 12 — the central Workflow Engine against a live MySQL 8 database. */
final class WorkflowEngineTest extends TestCase
{
    private Connection $connection;
    private WorkflowService $workflows;
    private WorkflowEngine $engine;

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

        $encrypter = new Encrypter('base64:' . base64_encode(str_repeat('k', 32)));
        $settings = new AiSettingsService($this->connection, $encrypter);
        $registry = new ProviderRegistry();
        $registry->register(new EchoProvider());
        $prompts = new PromptEngine($this->connection);
        $prompts->seedDefaults();
        $ai = new AiEngine($this->connection, $registry, $prompts, $settings);

        $this->workflows = new WorkflowService($this->connection);
        $actions = new ActionExecutor(
            $ai,
            new AuditLogger($this->connection),
            new TaskWriterAdapter(new TaskService($this->connection)),
            new NotificationWriterAdapter(new NotificationService($this->connection)),
            new NullRecruitmentActions(),
            new WorkflowCollectionService($this->connection),
        );
        $this->engine = new WorkflowEngine($this->connection, $this->workflows, $actions);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_trigger_runs_matching_workflow_and_records_execution_with_steps(): void
    {
        $ws = $this->workspace();

        $this->workflows->create($ws, 'AI screen applicants', 'application.submitted', [
            ['action' => 'audit', 'params' => ['message' => 'Application received']],
            ['action' => 'run_ai', 'params' => ['capability' => 'summarize_candidate']],
        ]);

        $ran = $this->engine->runForTrigger($ws, 'application.submitted', [
            'workspace_id' => $ws,
            'name' => 'Sara',
            'notes' => 'Strong PHP background',
        ]);

        $this->assertSame(1, $ran);

        $execution = $this->connection->selectOne('SELECT * FROM workflow_executions WHERE workspace_id = ?', [$ws]);
        $this->assertSame('completed', $execution['status']);
        $this->assertSame(2, (int) $execution['steps_total']);
        $this->assertSame(2, (int) $execution['steps_done']);

        $steps = $this->connection->select('SELECT * FROM workflow_steps WHERE execution_id = ? ORDER BY step_index ASC', [$execution['id']]);
        $this->assertCount(2, $steps);
        $this->assertSame('audit', $steps[0]['action']);
        $this->assertSame('completed', $steps[0]['status']);
        $this->assertSame('run_ai', $steps[1]['action']);
        $this->assertStringContainsString('ai(echo)', $steps[1]['output']);

        // The AI step routed through the central engine, leaving a session.
        $aiSession = $this->connection->selectOne('SELECT capability FROM ai_sessions WHERE workspace_id = ?', [$ws]);
        $this->assertSame('summarize_candidate', $aiSession['capability']);
    }

    public function test_only_enabled_published_workflows_for_the_trigger_run(): void
    {
        $ws = $this->workspace();

        // Right trigger — should run.
        $this->workflows->create($ws, 'On application', 'application.submitted', [
            ['action' => 'log', 'params' => ['message' => 'hi']],
        ]);
        // Different trigger — should be ignored.
        $this->workflows->create($ws, 'On hire', 'employee.hired', [
            ['action' => 'log', 'params' => ['message' => 'hi']],
        ]);

        $ran = $this->engine->runForTrigger($ws, 'application.submitted', ['workspace_id' => $ws]);

        $this->assertSame(1, $ran);
        $this->assertSame(1, (int) $this->connection->selectOne('SELECT COUNT(*) AS c FROM workflow_executions WHERE workspace_id = ?', [$ws])['c']);
    }

    public function test_workflows_are_isolated_per_workspace(): void
    {
        $a = $this->workspace();
        $b = $this->workspace();

        $this->workflows->create($a, 'Only in A', 'application.submitted', [
            ['action' => 'log', 'params' => ['message' => 'hi']],
        ]);

        // Firing the trigger in workspace B must not run A's workflow.
        $ran = $this->engine->runForTrigger($b, 'application.submitted', ['workspace_id' => $b]);

        $this->assertSame(0, $ran);
        $this->assertSame(0, (int) $this->connection->selectOne('SELECT COUNT(*) AS c FROM workflow_executions WHERE workspace_id = ?', [$b])['c']);
    }

    public function test_conditional_step_is_skipped_when_condition_not_met(): void
    {
        $ws = $this->workspace();

        $this->workflows->create($ws, 'Conditional', 'application.submitted', [
            ['action' => 'log', 'params' => ['message' => 'always'], 'condition' => ['field' => 'source', 'op' => 'equals', 'value' => 'referral']],
            ['action' => 'log', 'params' => ['message' => 'always too']],
        ]);

        $this->engine->runForTrigger($ws, 'application.submitted', ['workspace_id' => $ws, 'source' => 'careers_page']);

        $execution = $this->connection->selectOne('SELECT * FROM workflow_executions WHERE workspace_id = ?', [$ws]);
        $this->assertSame('completed', $execution['status']);
        // The conditional step is skipped, so only one of the two counts as done.
        $this->assertSame(1, (int) $execution['steps_done']);

        $skipped = $this->connection->selectOne("SELECT * FROM workflow_steps WHERE execution_id = ? AND status = 'skipped'", [$execution['id']]);
        $this->assertSame('log', $skipped['action']);
    }

    public function test_create_task_action_creates_a_real_workspace_task_with_resolved_tokens(): void
    {
        $ws = $this->workspace();
        $this->workflows->create($ws, 'Task on apply', 'application.submitted', [
            ['action' => 'workspace.create_task', 'params' => ['title' => 'Review {{candidate_name}}']],
        ]);

        $this->engine->runForTrigger($ws, 'application.submitted', ['workspace_id' => $ws, 'candidate_name' => 'Sara']);

        $task = $this->connection->selectOne('SELECT * FROM tasks WHERE workspace_id = ?', [$ws]);
        $this->assertNotNull($task);
        $this->assertSame('Review Sara', $task['title']); // {{candidate_name}} token resolved from the payload
    }

    public function test_notify_action_sends_an_in_app_notification_via_the_contract(): void
    {
        $ws = $this->workspace();
        $userId = (string) $this->connection->selectOne('SELECT owner_user_id FROM workspaces WHERE id = ?', [$ws])['owner_user_id'];

        $this->workflows->create($ws, 'Notify on apply', 'application.submitted', [
            ['action' => 'users.notify', 'params' => ['user_id' => '{{user_id}}', 'title' => 'New application received']],
        ]);

        $this->engine->runForTrigger($ws, 'application.submitted', ['workspace_id' => $ws, 'user_id' => $userId]);

        $note = $this->connection->selectOne('SELECT * FROM notifications WHERE workspace_id = ? AND user_id = ?', [$ws, $userId]);
        $this->assertNotNull($note);
        $this->assertSame('New application received', $note['title']);
    }

    public function test_module_boot_listener_runs_workflow_on_dispatched_event(): void
    {
        $ws = $this->workspace();
        $this->workflows->create($ws, 'On apply', 'application.submitted', [
            ['action' => 'audit', 'params' => ['message' => 'Application received']],
            ['action' => 'run_ai', 'params' => ['capability' => 'summarize_candidate']],
        ]);

        // A minimal container holding exactly what the engine graph autowires.
        $container = new Container();
        $container->instance(Connection::class, $this->connection);
        $container->instance(EventDispatcher::class, new Dispatcher());
        // Logging is consumed via the AuditRecorder contract (ARCHITECTURE.md §4).
        $container->instance(\HaHireAI\Core\Contracts\AuditRecorder::class, new AuditLogger($this->connection));
        // The engine's action write-surfaces — Null defaults as the Kernel binds.
        $container->instance(\HaHireAI\Core\Contracts\TaskWriter::class, new NullTaskWriter());
        $container->instance(\HaHireAI\Core\Contracts\NotificationWriter::class, new NullNotificationWriter());
        $container->instance(\HaHireAI\Core\Contracts\RecruitmentActions::class, new NullRecruitmentActions());

        $registry = new ProviderRegistry();
        $registry->register(new EchoProvider());
        $prompts = new PromptEngine($this->connection);
        $prompts->seedDefaults();
        $container->instance(ProviderRegistry::class, $registry);
        $container->instance(PromptEngine::class, $prompts);
        $container->instance(AiSettingsService::class, new AiSettingsService(
            $this->connection,
            new Encrypter('base64:' . base64_encode(str_repeat('k', 32))),
        ));

        // Register the listener exactly as the Kernel does at boot.
        (new WorkflowModule())->boot($container);

        $dispatcher = $container->make(EventDispatcher::class);
        $this->assertTrue($dispatcher->hasListeners('application.submitted'));

        // Simulate PublicJobController publishing the domain event on apply().
        $dispatcher->dispatch('application.submitted', [
            'workspace_id' => $ws,
            'user_id' => null,
            'candidate_name' => 'Sara',
            'notes' => 'Strong PHP background',
        ]);

        $execution = $this->connection->selectOne('SELECT * FROM workflow_executions WHERE workspace_id = ?', [$ws]);
        $this->assertNotNull($execution);
        $this->assertSame('completed', $execution['status']);
        $this->assertSame(2, (int) $execution['steps_done']);

        // And the AI step really routed through the central engine.
        $this->assertSame(
            'summarize_candidate',
            $this->connection->selectOne('SELECT capability FROM ai_sessions WHERE workspace_id = ?', [$ws])['capability'],
        );
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
