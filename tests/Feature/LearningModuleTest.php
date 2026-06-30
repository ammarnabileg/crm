<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Contracts\MemberDirectory;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Core\Events\Dispatcher;
use HaHireAI\Modules\Learning\Application\CertificateService;
use HaHireAI\Modules\Learning\Application\CommentService;
use HaHireAI\Modules\Learning\Application\EnrollmentService;
use HaHireAI\Modules\Learning\Application\LearningPathService;
use HaHireAI\Modules\Learning\Application\PrerequisiteService;
use HaHireAI\Modules\Learning\Application\ProgramService;
use HaHireAI\Modules\Learning\Application\QuizService;
use HaHireAI\Modules\Learning\Application\TodoService;
use HaHireAI\Modules\Learning\Domain\ItemType;
use HaHireAI\Modules\Learning\Domain\TodoStatus;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * The Learning module end to end on live MySQL: authoring (program → sections →
 * items), publishing, assignment fan-out + enrollment + progress/completion,
 * manager vs self to-dos, comments, and absolute workspace isolation.
 */
final class LearningModuleTest extends TestCase
{
    private Connection $connection;
    private ProgramService $programs;
    private EnrollmentService $enrollments;
    private TodoService $todos;
    private CommentService $comments;
    private QuizService $quizzes;
    private CertificateService $certificates;
    private PrerequisiteService $prerequisites;
    private LearningPathService $paths;
    private Dispatcher $events;

    /** @var array<string, list<string>> roleId => userIds, for the fake directory */
    private array $roleMembers = [];

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

        $this->events = new Dispatcher();
        $this->programs = new ProgramService($this->connection);
        $this->todos = new TodoService($this->connection);
        $this->comments = new CommentService($this->connection);
        $this->quizzes = new QuizService($this->connection);
        $this->certificates = new CertificateService($this->connection);
        $this->prerequisites = new PrerequisiteService($this->connection);
        $this->paths = new LearningPathService($this->connection);
        $this->enrollments = new EnrollmentService($this->connection, $this->programs, $this->fakeMembers(), $this->events, $this->certificates);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_program_authoring_and_structure(): void
    {
        [$ws, $owner] = $this->workspace();
        $pid = $this->programs->create($ws, $owner, ['title' => 'Software Developer Onboarding', 'tags' => 'eng, onboarding', 'difficulty' => 'intermediate']);

        $program = $this->programs->find($ws, $pid);
        $this->assertNotNull($program);
        $this->assertSame('draft', $program['status']);
        $this->assertSame('software-developer-onboarding', $program['slug']);
        $this->assertSame(['eng', 'onboarding'], $this->programs->tagsFor($ws, $pid));

        $s1 = $this->programs->addSection($ws, $pid, $owner, 'Week 1', null, true);
        $this->programs->addItem($ws, $pid, $s1, $owner, ['type' => ItemType::LESSON, 'title' => 'Company culture', 'body' => 'Welcome']);
        $this->programs->addItem($ws, $pid, $s1, $owner, ['type' => ItemType::LINK, 'title' => 'Handbook', 'url' => 'https://example.com', 'is_required' => false]);

        $structure = $this->programs->structure($ws, $pid);
        $this->assertCount(1, $structure);
        $this->assertCount(2, $structure[0]['items']);
        $this->assertCount(2, $this->programs->items($ws, $pid));
    }

    public function test_publish_enroll_and_progress_to_completion(): void
    {
        [$ws, $owner] = $this->workspace();
        $learner = $this->user('Lina');
        $pid = $this->programs->create($ws, $owner, ['title' => 'Security Awareness', 'completion_rule' => 'required_items']);
        $s = $this->programs->addSection($ws, $pid, $owner, 'Module', null, true);
        $i1 = $this->programs->addItem($ws, $pid, $s, $owner, ['title' => 'Phishing', 'is_required' => true]);
        $i2 = $this->programs->addItem($ws, $pid, $s, $owner, ['title' => 'Passwords', 'is_required' => true]);
        $i3 = $this->programs->addItem($ws, $pid, $s, $owner, ['title' => 'Bonus reading', 'is_required' => false]);
        $this->programs->setStatus($ws, $pid, 'published', $owner);

        $this->assertTrue($this->enrollments->enroll($ws, $pid, $learner));
        $this->assertFalse($this->enrollments->enroll($ws, $pid, $learner), 'enroll is idempotent');

        $this->enrollments->setItemStatus($ws, $pid, $learner, $i1, 'completed');
        $e = $this->enrollments->enrollment($ws, $pid, $learner);
        $this->assertSame('in_progress', $e['status']);
        $this->assertSame(33, (int) $e['progress_percent']);

        // Completing both REQUIRED items satisfies the required_items rule.
        $this->enrollments->setItemStatus($ws, $pid, $learner, $i2, 'completed');
        $e = $this->enrollments->enrollment($ws, $pid, $learner);
        $this->assertSame('completed', $e['status']);
        $this->assertNotNull($e['completed_at']);
    }

    public function test_assignment_fans_out_to_role_members(): void
    {
        [$ws, $owner] = $this->workspace();
        $a = $this->user('A');
        $b = $this->user('B');
        $roleId = Ulid::generate();
        $this->roleMembers[$roleId] = [$a, $b];

        $pid = $this->programs->create($ws, $owner, ['title' => 'Sales Training']);
        $created = $this->enrollments->assign($ws, $pid, 'role', $roleId, $owner, null);

        $this->assertSame(2, $created);
        $this->assertNotNull($this->enrollments->enrollment($ws, $pid, $a));
        $this->assertNotNull($this->enrollments->enrollment($ws, $pid, $b));
        $this->assertCount(2, $this->enrollments->roster($ws, $pid)['enrollments']);
    }

    public function test_manager_controlled_todo_blocks_self_completion(): void
    {
        [$ws, $owner] = $this->workspace();
        $assignee = $this->user('Worker');
        $pid = $this->programs->create($ws, $owner, ['title' => 'Performance Improvement']);
        $todoId = $this->todos->create($ws, $pid, $owner, [
            'title' => 'Submit self-review', 'completion_mode' => TodoStatus::MODE_MANAGER, 'assignee_user_id' => $assignee,
        ]);

        // The assignee (no manage permission) cannot close a manager-controlled to-do.
        $blocked = $this->todos->changeStatus($ws, $todoId, 'done', $assignee, false);
        $this->assertFalse($blocked['ok']);
        $this->assertSame('open', $this->todos->find($ws, $todoId)['status']);

        // A supervisor (manage) can.
        $ok = $this->todos->changeStatus($ws, $todoId, 'done', $owner, true);
        $this->assertTrue($ok['ok']);
        $this->assertSame('done', $this->todos->find($ws, $todoId)['status']);
        // Status history captured both the create and the completion.
        $this->assertGreaterThanOrEqual(2, count($this->todos->statusHistory($ws, $todoId)));
    }

    public function test_self_todo_completes_by_assignee(): void
    {
        [$ws, $owner] = $this->workspace();
        $assignee = $this->user('Self');
        $pid = $this->programs->create($ws, $owner, ['title' => 'Reading list']);
        $todoId = $this->todos->create($ws, $pid, $owner, ['title' => 'Read', 'completion_mode' => TodoStatus::MODE_SELF, 'assignee_user_id' => $assignee]);

        $this->assertTrue($this->todos->changeStatus($ws, $todoId, 'done', $assignee, false)['ok']);
    }

    public function test_quiz_authoring_grading_and_completion(): void
    {
        [$ws, $owner] = $this->workspace();
        $learner = $this->user('Quiz Taker');
        $pid = $this->programs->create($ws, $owner, ['title' => 'Security Quiz', 'completion_rule' => 'required_items']);
        $s = $this->programs->addSection($ws, $pid, $owner, 'Test', null, true);
        $quizItem = $this->programs->addItem($ws, $pid, $s, $owner, ['type' => ItemType::QUIZ, 'title' => 'Final check', 'is_required' => true]);

        $q1 = $this->quizzes->addQuestion($ws, $quizItem, 'Capital of France?', 'single');
        $paris = $this->quizzes->addOption($ws, $q1, 'Paris', true);
        $this->quizzes->addOption($ws, $q1, 'Berlin', false);
        $q2 = $this->quizzes->addQuestion($ws, $quizItem, '2 + 2 = ?', 'single');
        $four = $this->quizzes->addOption($ws, $q2, '4', true);
        $this->quizzes->addOption($ws, $q2, '5', false);

        $this->assertCount(2, $this->quizzes->questionsFor($ws, $quizItem));

        // A failing attempt does not complete the item.
        $fail = $this->quizzes->submit($ws, $pid, $quizItem, $learner, [$q1 => [$paris]], 70);
        $this->assertSame(50, $fail['percent']);
        $this->assertFalse($fail['passed']);

        // A passing attempt grades 100% and (via the controller flow) completes it.
        $pass = $this->quizzes->submit($ws, $pid, $quizItem, $learner, [$q1 => [$paris], $q2 => [$four]], 70);
        $this->assertSame(100, $pass['percent']);
        $this->assertTrue($pass['passed']);
        $this->enrollments->setItemStatus($ws, $pid, $learner, $quizItem, 'completed', $learner);
        $this->assertSame('completed', $this->enrollments->enrollment($ws, $pid, $learner)['status']);

        // bestAttempt returns the 100% one.
        $this->assertSame(100, (int) $this->quizzes->bestAttempt($ws, $quizItem, $learner)['percent']);
    }

    public function test_certificate_issued_on_completion(): void
    {
        [$ws, $owner] = $this->workspace();
        $learner = $this->user('Grad');
        $pid = $this->programs->create($ws, $owner, ['title' => 'Onboarding', 'completion_rule' => 'all_items']);
        $s = $this->programs->addSection($ws, $pid, $owner, 'M', null, true);
        $i1 = $this->programs->addItem($ws, $pid, $s, $owner, ['title' => 'Read', 'is_required' => true]);
        $this->programs->setStatus($ws, $pid, 'published', $owner);

        $this->assertNull($this->certificates->forProgram($ws, $pid, $learner));
        $this->enrollments->setItemStatus($ws, $pid, $learner, $i1, 'completed');

        $cert = $this->certificates->forProgram($ws, $pid, $learner);
        $this->assertNotNull($cert);
        $this->assertSame(100, (int) $cert['percent']);
        $this->assertNotNull($this->certificates->findBySerial($ws, (string) $cert['serial']));
        $this->assertCount(1, $this->certificates->forUser($ws, $learner));
    }

    public function test_prerequisites_block_until_complete(): void
    {
        [$ws, $owner] = $this->workspace();
        $learner = $this->user('Seq');
        $basics = $this->programs->create($ws, $owner, ['title' => 'Basics', 'completion_rule' => 'all_items']);
        $advanced = $this->programs->create($ws, $owner, ['title' => 'Advanced']);
        $this->assertTrue($this->prerequisites->add($ws, $advanced, $basics));
        $this->assertFalse($this->prerequisites->add($ws, $advanced, $advanced), 'no self-prerequisite');

        // Not satisfied until Basics is completed.
        $this->assertFalse($this->prerequisites->isSatisfied($ws, $advanced, $learner));
        $this->assertCount(1, $this->prerequisites->unmetFor($ws, $advanced, $learner));

        $s = $this->programs->addSection($ws, $basics, $owner, 'M', null, true);
        $i = $this->programs->addItem($ws, $basics, $s, $owner, ['title' => 'X', 'is_required' => true]);
        $this->enrollments->setItemStatus($ws, $basics, $learner, $i, 'completed');

        $this->assertTrue($this->prerequisites->isSatisfied($ws, $advanced, $learner));
    }

    public function test_learning_path_progress(): void
    {
        [$ws, $owner] = $this->workspace();
        $learner = $this->user('Pather');
        $p1 = $this->programs->create($ws, $owner, ['title' => 'P1', 'completion_rule' => 'all_items']);
        $p2 = $this->programs->create($ws, $owner, ['title' => 'P2']);
        $pathId = $this->paths->create($ws, $owner, 'Track', 'desc');
        $this->paths->addProgram($ws, $pathId, $p1);
        $this->paths->addProgram($ws, $pathId, $p2);

        $this->assertCount(2, $this->paths->programsFor($ws, $pathId));
        $this->assertSame(0, $this->paths->progressFor($ws, $pathId, $learner)['percent']);

        // Complete P1 → 50% of the path.
        $s = $this->programs->addSection($ws, $p1, $owner, 'M', null, true);
        $i = $this->programs->addItem($ws, $p1, $s, $owner, ['title' => 'X', 'is_required' => true]);
        $this->enrollments->setItemStatus($ws, $p1, $learner, $i, 'completed');
        $this->assertSame(50, $this->paths->progressFor($ws, $pathId, $learner)['percent']);
    }

    public function test_comments_thread_with_replies(): void
    {
        [$ws, $owner] = $this->workspace();
        $pid = $this->programs->create($ws, $owner, ['title' => 'Culture']);
        $c1 = $this->comments->add($ws, $pid, 'program', $pid, $owner, 'Welcome everyone');
        $this->assertNotNull($c1);
        $this->comments->add($ws, $pid, 'program', $pid, $owner, 'Glad to be here', $c1);

        $thread = $this->comments->thread($ws, 'program', $pid);
        $this->assertCount(1, $thread);
        $this->assertCount(1, $thread[0]['replies']);
        $this->assertSame(2, $this->comments->countForEntity($ws, 'program', $pid));
    }

    public function test_workspace_isolation(): void
    {
        [$ws1, $owner1] = $this->workspace();
        [$ws2] = $this->workspace();
        $pid = $this->programs->create($ws1, $owner1, ['title' => 'Private']);

        $this->assertNotNull($this->programs->find($ws1, $pid));
        $this->assertNull($this->programs->find($ws2, $pid), 'another workspace must not see the program');
        $this->assertSame([], $this->programs->listForWorkspace($ws2));
    }

    // --- helpers -----------------------------------------------------------

    /** @return array{0:string,1:string} [workspaceId, ownerId] */
    private function workspace(): array
    {
        $owner = $this->user('Owner');
        $ws = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$ws, 'Acme', 'acme-' . substr($ws, -6), $owner, $now, $now],
        );

        return [$ws, $owner];
    }

    private function user(string $name): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $name, strtolower($name) . '-' . substr($id, -6) . '@x.co', 'x', $now, $now],
        );

        return $id;
    }

    private function fakeMembers(): MemberDirectory
    {
        $roleMembers = &$this->roleMembers;

        return new class($roleMembers) implements MemberDirectory {
            /** @param array<string, list<string>> $roleMembers */
            public function __construct(private array &$roleMembers)
            {
            }

            public function membersWithRole(string $workspaceId, string $roleId): array
            {
                return $this->roleMembers[$roleId] ?? [];
            }

            public function create(string $workspaceId, string $userId, string $status = 'active', ?string $invitedBy = null): string
            {
                return '';
            }

            public function findById(string $membershipId): ?array
            {
                return null;
            }

            public function find(string $workspaceId, string $userId): ?array
            {
                return null;
            }

            public function countForWorkspace(string $workspaceId): int
            {
                return 0;
            }

            public function workspacesForUser(string $userId): array
            {
                return [];
            }

            public function workspacesForUserDetailed(string $userId): array
            {
                return [];
            }

            public function membersForWorkspace(string $workspaceId): array
            {
                return [];
            }

            public function setStatus(string $workspaceId, string $membershipId, string $status): bool
            {
                return true;
            }

            public function remove(string $workspaceId, string $membershipId): bool
            {
                return true;
            }

            public function touchActivity(string $membershipId): void
            {
            }
        };
    }

    private function wipe(): void
    {
        $this->connection->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'learning_path_programs', 'learning_paths', 'learning_prerequisites', 'learning_certificates',
            'learning_activity', 'learning_quiz_answers', 'learning_quiz_attempts',
            'learning_quiz_options', 'learning_quiz_questions', 'learning_program_versions',
            'learning_program_editors', 'learning_item_progress', 'learning_enrollments', 'learning_assignments',
            'learning_attachments', 'learning_comment_mentions', 'learning_comments', 'learning_todo_status_history',
            'learning_todos', 'learning_items', 'learning_sections', 'learning_program_tags', 'learning_programs',
            'workspaces', 'users',
        ] as $table) {
            try {
                $this->connection->statement("DELETE FROM {$table}");
            } catch (\Throwable) {
                // table may not exist yet on the very first run
            }
        }
        $this->connection->statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
