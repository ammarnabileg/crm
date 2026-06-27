<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Cv\CvService;
use App\Services\Tenancy\WorkspaceService;
use Tests\TestCase;

/**
 * CvService — extract → parse → persist. Proves a parsed CV produces a `cv_parses`
 * record, enriches the candidate's global profile (filling blanks) and creates
 * searchable skills in the workspace, all without throwing on a real file.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private int $userId = 0;
    private int $workspaceId = 0;
    private string $path = '';

    public function setUp(): void
    {
        $owner = User::create([
            'name'           => 'CV Owner',
            'email'          => 'cv-' . uniqid() . '@cv.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->workspaceId = (int) (new WorkspaceService())->create($owner, 'CV Co')->getKey();

        $candidate = User::create([
            'name'           => 'Candidate',
            'email'          => 'cand-' . uniqid() . '@cv.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->userId = (int) $candidate->getKey();

        tenant()->setById($this->workspaceId);

        $this->path = sys_get_temp_dir() . '/cv-' . bin2hex(random_bytes(4)) . '.txt';
        file_put_contents(
            $this->path,
            "Khaled Said\nkhaled.said@example.com\n+20 100 555 7777\n"
            . "Senior Software Engineer with 7 years of experience.\n"
            . "Skills: PHP, Laravel, Docker, MySQL"
        );
    }

    public function tearDown(): void
    {
        @unlink($this->path);
        tenant()->clear();
    }

    public function test_parse_file_persists_parse_profile_and_skills(): void
    {
        $result = (new CvService())->parseFile($this->userId, $this->path, 'cv.txt', $this->workspaceId, null);

        $this->assertTrue($result['stored']);
        $this->assertSame('heuristic', $result['parse']['source']); // no AI key configured

        // A cv_parses row was written with the JSON payload.
        $row = app('db')->table('cv_parses')->where('user_id', '=', $this->userId)->first();
        $this->assertNotNull($row);
        $this->assertSame('heuristic', (string) $row['source']);
        $this->assertTrue(str_contains((string) $row['data'], 'khaled.said@example.com'));

        // The candidate's global profile was enriched (years filled from the CV).
        $profile = app('db')->table('candidate_profiles')->where('user_id', '=', $this->userId)->first();
        $this->assertNotNull($profile);
        $this->assertSame(7.0, (float) $profile['total_experience_years']);

        // Skills became searchable: workspace skills + candidate_skills links.
        $linked = app('db')->table('candidate_skills')->where('user_id', '=', $this->userId)->count();
        $this->assertTrue($linked >= 3, 'Parsed skills are linked to the candidate.');
        $this->assertTrue(
            app('db')->table('skills')->where('workspace_id', '=', $this->workspaceId)
                ->whereRaw('LOWER(`name`) = ?', ['php'])->exists()
        );

        // latestForUser surfaces it for the recruiter UI.
        $latest = (new CvService())->latestForUser($this->userId);
        $this->assertNotNull($latest);
        $this->assertSame('heuristic', $latest['source']);
        $this->assertTrue(in_array('PHP', $latest['data']['skills'] ?? [], true));
    }

    public function test_re_parsing_does_not_duplicate_skill_links(): void
    {
        $svc = new CvService();
        $svc->parseFile($this->userId, $this->path, 'cv.txt', $this->workspaceId, null);
        $first = app('db')->table('candidate_skills')->where('user_id', '=', $this->userId)->count();

        $svc->parseFile($this->userId, $this->path, 'cv.txt', $this->workspaceId, null);
        $second = app('db')->table('candidate_skills')->where('user_id', '=', $this->userId)->count();

        $this->assertSame($first, $second, 'Re-parsing must not duplicate candidate_skills (PK user+skill).');
    }
};
