<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Model;
use App\Services\Ats\AdvancedSearch;
use Tests\TestCase;

/**
 * Advanced Search service (docs/53 ATS Advanced Search & Filtering). Builds a
 * job + applications + a candidate profile + a skill in-tx, then exercises the
 * supported application/candidate filters (job, status, score, source, recruiter,
 * stage, experience, availability, expected salary, skills). All rolled back.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private int $jobId = 0;
    private int $userId = 0;
    private int $appId = 0;

    public function setUp(): void
    {
        tenant()->setById((int) app('db')->table('workspaces')->orderBy('id')->value('id'));
    }

    private function service(): AdvancedSearch
    {
        return new AdvancedSearch();
    }

    /** Build a job + a single application for the first user; cache ids. */
    private function seed(): void
    {
        $db = app('db');
        $workspaceId = (int) tenant()->id();
        $this->userId = (int) $db->table('users')->orderBy('id')->value('id');
        $now = now();

        $this->jobId = $db->table('jobs')->insertGetId([
            'uuid'          => Model::generateUuid(),
            'workspace_id'  => $workspaceId,
            'job_status_id' => status_id('job_statuses', 'open'),
            'title'         => 'Search Test Job',
            'slug'          => 'search-' . substr(Model::generateUuid(), 0, 12),
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        $this->appId = $db->table('applications')->insertGetId([
            'uuid'                  => Model::generateUuid(),
            'workspace_id'          => $workspaceId,
            'job_id'                => $this->jobId,
            'user_id'               => $this->userId,
            'application_status_id' => status_id('application_statuses', 'applied'),
            'source_id'             => null,
            'score'                 => 82.50,
            'applied_at'            => $now,
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);
    }

    private function makeProfile(array $overrides = []): void
    {
        $db = app('db');
        $now = now();
        $db->table('candidate_profiles')->insertGetId(array_merge([
            'uuid'                   => Model::generateUuid(),
            'user_id'                => $this->userId,
            'total_experience_years' => 6.0,
            'availability_id'        => null,
            'expected_salary'        => 9000.00,
            'is_open_to_work'        => 1,
            'is_searchable'          => 1,
            'created_at'             => $now,
            'updated_at'             => $now,
        ], $overrides));
    }

    // --- searchApplications ------------------------------------------------

    public function test_search_by_job_returns_the_application(): void
    {
        $this->seed();
        $rows = $this->service()->searchApplications(['job_id' => $this->jobId]);

        $this->assertSame(1, count($rows));
        $this->assertSame($this->appId, (int) $rows[0]['id']);
    }

    public function test_search_by_status_key_resolves_to_status_id(): void
    {
        $this->seed();
        $hit = $this->service()->searchApplications([
            'job_id'             => $this->jobId,
            'application_status' => 'applied',
        ]);
        $miss = $this->service()->searchApplications([
            'job_id'             => $this->jobId,
            'application_status' => 'rejected',
        ]);

        $this->assertSame(1, count($hit));
        $this->assertSame(0, count($miss));
    }

    public function test_search_by_min_score(): void
    {
        $this->seed();
        $hit = $this->service()->searchApplications(['job_id' => $this->jobId, 'min_score' => 80]);
        $miss = $this->service()->searchApplications(['job_id' => $this->jobId, 'min_score' => 90]);

        $this->assertSame(1, count($hit));
        $this->assertSame(0, count($miss));
    }

    public function test_search_is_tenant_scoped_by_job(): void
    {
        $this->seed();
        // A job id that does not exist in this tenant yields nothing.
        $rows = $this->service()->searchApplications(['job_id' => $this->jobId + 999999]);
        $this->assertSame(0, count($rows));
    }

    public function test_application_search_narrowed_by_experience_profile(): void
    {
        $this->seed();
        $this->makeProfile(['total_experience_years' => 6.0]);

        $hit = $this->service()->searchApplications(['job_id' => $this->jobId, 'min_experience' => 5]);
        $miss = $this->service()->searchApplications(['job_id' => $this->jobId, 'min_experience' => 10]);

        $this->assertSame(1, count($hit));
        $this->assertSame(0, count($miss));
    }

    // --- searchCandidates --------------------------------------------------

    public function test_candidate_search_by_experience_and_salary(): void
    {
        $this->seed();
        $this->makeProfile(['total_experience_years' => 6.0, 'expected_salary' => 9000.00]);

        $hit = $this->service()->searchCandidates([
            'user_id'             => $this->userId,
            'min_experience'      => 5,
            'max_expected_salary' => 10000,
        ]);
        $this->assertSame(1, count($hit));
        $this->assertSame($this->userId, (int) $hit[0]['user_id']);

        $miss = $this->service()->searchCandidates([
            'user_id'             => $this->userId,
            'max_expected_salary' => 8000,
        ]);
        $this->assertSame(0, count($miss));
    }

    public function test_candidate_search_searchable_gate_excludes_hidden(): void
    {
        $this->seed();
        $this->makeProfile(['is_searchable' => 0]);

        // Default gate excludes non-searchable profiles.
        $gated = $this->service()->searchCandidates(['user_id' => $this->userId]);
        $this->assertSame(0, count($gated));

        // Explicit override surfaces it.
        $ungated = $this->service()->searchCandidates(['user_id' => $this->userId, 'is_searchable' => false]);
        $this->assertSame(1, count($ungated));
    }

    public function test_candidate_search_by_skill_id(): void
    {
        $this->seed();
        $this->makeProfile();

        $db = app('db');
        $now = now();
        $skillId = $db->table('skills')->insertGetId([
            'uuid'         => Model::generateUuid(),
            'workspace_id' => (int) tenant()->id(),
            'name'         => 'PHP-' . substr(Model::generateUuid(), 0, 8),
            'slug'         => 'php-' . substr(Model::generateUuid(), 0, 8),
            'is_system'    => 0,
            'is_active'    => 1,
            'usage_count'  => 0,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
        $db->table('candidate_skills')->insert([
            'user_id'    => $this->userId,
            'skill_id'   => $skillId,
            'is_primary' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $hit = $this->service()->searchCandidates(['user_id' => $this->userId, 'skills' => [$skillId]]);
        $this->assertSame(1, count($hit));

        $miss = $this->service()->searchCandidates(['user_id' => $this->userId, 'skills' => [$skillId + 987654]]);
        $this->assertSame(0, count($miss));
    }

    public function test_empty_filters_respects_limit_and_returns_array(): void
    {
        $this->seed();
        $rows = $this->service()->searchApplications(['limit' => 5]);
        $this->assertTrue(is_array($rows));
        $this->assertTrue(count($rows) <= 5);
    }
};
