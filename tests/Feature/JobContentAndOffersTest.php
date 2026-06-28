<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Recruitment\Application\ApplicationService;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Modules\Recruitment\Application\Exceptions\ApplicationException;
use HaHireAI\Modules\Recruitment\Application\JobContentService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Modules\Recruitment\Application\OfferService;
use HaHireAI\Shared\Ulid;
use PHPUnit\Framework\TestCase;

/** Job question bank + criteria/rubric (spec #2/#4) and staff offer transitions (#10/#13). */
final class JobContentAndOffersTest extends TestCase
{
    private Connection $connection;
    private JobService $jobs;
    private JobContentService $content;
    private ApplicationService $applications;
    private OfferService $offers;

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

        $this->jobs = new JobService($this->connection);
        $this->content = new JobContentService($this->connection);
        $this->applications = new ApplicationService($this->connection, $this->jobs, new CandidateProfileService($this->connection));
        $this->offers = new OfferService($this->connection);
    }

    protected function tearDown(): void
    {
        $this->wipe();
    }

    public function test_question_bank_is_ordered_and_workspace_scoped(): void
    {
        [$ws, $owner] = $this->workspace();
        $jobId = $this->jobs->create($ws, $owner, 'Engineer');
        $this->content->addQuestion($ws, $jobId, 'Q1?');
        $this->content->addQuestion($ws, $jobId, 'Q2?');

        $this->assertSame(['Q1?', 'Q2?'], $this->content->questionTexts($ws, $jobId));

        [$wsB] = $this->workspace('b@x.co');
        $this->assertSame([], $this->content->questionTexts($wsB, $jobId));
    }

    public function test_criteria_weight_is_clamped(): void
    {
        [$ws, $owner] = $this->workspace();
        $jobId = $this->jobs->create($ws, $owner, 'Engineer');
        $this->content->addCriterion($ws, $jobId, 'System design', 999);

        $rows = $this->content->criteria($ws, $jobId);
        $this->assertCount(1, $rows);
        $this->assertSame(100, (int) $rows[0]['weight']);
    }

    public function test_clone_duplicates_job_with_questions_and_criteria(): void
    {
        [$ws, $owner] = $this->workspace();
        $jobId = $this->jobs->create($ws, $owner, 'Engineer', 'desc', 'Cairo', 'full_time', ['seniority' => 'senior']);
        $this->content->addQuestion($ws, $jobId, 'Q1?');
        $this->content->addCriterion($ws, $jobId, 'System design', 30);

        $newId = $this->jobs->clone($ws, $jobId, $owner);
        $this->assertNotNull($newId);
        $clone = $this->jobs->find($ws, $newId);
        $this->assertSame('Engineer (Copy)', $clone['title']);
        $this->assertSame('draft', $clone['status']);
        $this->assertSame(['Q1?'], $this->content->questionTexts($ws, $newId));
        $this->assertCount(1, $this->content->criteria($ws, $newId));
    }

    public function test_list_filters_by_search_and_status(): void
    {
        [$ws, $owner] = $this->workspace();
        $a = $this->jobs->create($ws, $owner, 'Backend Engineer');
        $this->jobs->publish($ws, $a);
        $this->jobs->create($ws, $owner, 'Product Designer');           // draft

        $this->assertCount(1, $this->jobs->listForWorkspace($ws, ['q' => 'Engineer']));
        $this->assertCount(1, $this->jobs->listForWorkspace($ws, ['status' => 'published']));
        $this->assertCount(1, $this->jobs->listForWorkspace($ws, ['status' => 'draft']));
        $this->assertCount(2, $this->jobs->listForWorkspace($ws));
    }

    public function test_job_update_and_archive(): void
    {
        [$ws, $owner] = $this->workspace();
        $jobId = $this->jobs->create($ws, $owner, 'Engineer');
        $this->jobs->update($ws, $jobId, ['title' => 'Senior Engineer', 'seniority' => 'senior']);
        $this->assertSame('Senior Engineer', $this->jobs->find($ws, $jobId)['title']);

        $this->jobs->archive($ws, $jobId);
        $this->assertNull($this->jobs->find($ws, $jobId));     // soft-deleted
        $this->assertCount(0, $this->jobs->listPublished($ws));
    }

    public function test_offer_withdraw_and_list(): void
    {
        [$ws, $owner] = $this->workspace();
        $cand = $this->user('cand@x.co');
        $jobId = $this->jobs->create($ws, $owner, 'Engineer');
        $appId = $this->applications->apply($ws, $jobId, $cand);
        $offerId = $this->offers->create($ws, $appId, 'Engineer', 5000, 'USD', $owner);
        $this->offers->send($ws, $offerId);

        $this->offers->withdraw($ws, $offerId);
        $this->assertSame('revoked', $this->offers->find($ws, $offerId)['status']);

        $list = $this->offers->listForWorkspace($ws);
        $this->assertCount(1, $list);
        $this->assertSame('Engineer', $list[0]['job_title']);

        // An accepted offer can no longer be withdrawn.
        $o2 = $this->offers->create($ws, $appId, 'Engineer', 5000, 'USD', $owner);
        $this->offers->send($ws, $o2);
        $this->offers->accept($ws, $o2);
        $this->expectException(ApplicationException::class);
        $this->offers->withdraw($ws, $o2);
    }

    /** @return array{0:string,1:string} */
    private function workspace(string $ownerEmail = 'owner@x.co'): array
    {
        $owner = $this->user($ownerEmail);
        $workspaceId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workspaces (id, name, slug, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$workspaceId, 'Acme', 'acme-' . substr($workspaceId, -6), $owner, $now, $now],
        );

        return [$workspaceId, $owner];
    }

    private function user(string $email): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$id, 'User', $email . '.' . substr($id, -4), 'x', $now, $now],
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
