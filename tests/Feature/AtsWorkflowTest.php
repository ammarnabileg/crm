<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Model;
use App\Models\Application;
use App\Models\Job;
use App\Models\Offer;
use App\Services\Ats\ApplicationFlow;
use App\Services\Ats\InterviewScheduler;
use App\Services\Ats\JobManager;
use App\Services\Ats\OfferManager;
use App\Services\Ats\PipelineManager;
use App\Services\Evaluation\DecisionEngine;
use App\Services\Interview\StateMachine;
use Tests\TestCase;

/**
 * ATS self-validation (docs/53): the full recruitment lifecycle end to end —
 * Create Job → Publish → Apply → Review/Move → Assign Recruiter → Schedule Interview
 * → Run AI Interview → Generate Report → Create Offer → Accept Offer → Reject
 * (another candidate) → Archive Job. Ties the ATS to the AI Interview Engine
 * (StateMachine + DecisionEngine), proving the AI engine is one stage of the ATS.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private int $userId = 0;
    private int $otherUserId = 0;

    public function setUp(): void
    {
        $db = app('db');
        tenant()->setById((int) $db->table('workspaces')->orderBy('id')->value('id'));
        $ids = array_map(static fn ($r) => (int) $r['id'], $db->table('users')->orderBy('id')->get());
        $this->userId = $ids[0];
        $this->otherUserId = $ids[1] ?? $ids[0];
    }

    public function test_full_recruitment_lifecycle(): void
    {
        $jobs = new JobManager();
        $pipelines = new PipelineManager();
        $flow = new ApplicationFlow($pipelines);
        $offers = new OfferManager();
        $scheduler = new InterviewScheduler();

        // 1) Create Job (draft).
        $job = $jobs->create(['title' => 'Senior Backend Engineer', 'openings' => 2], $this->userId);
        $jobId = (int) $job->getKey();
        $this->assertSame('draft', $job->statusKey());

        // 2) Publish Job.
        $jobs->publish($jobId);
        $this->assertSame('open', Job::find($jobId)->statusKey());

        // Default pipeline (11+ standard stages, attached to the job).
        $pipeline = $pipelines->createDefault($jobId, $this->userId);
        $stages = $pipeline->stages();
        $this->assertTrue(count($stages) >= 11);
        $this->assertSame((int) $pipeline->getKey(), (int) Job::find($jobId)->pipeline_id);

        // 3) Apply (lands on the initial stage).
        $app = $flow->apply($jobId, $this->userId, ['source_id' => lookup_id('application_source', 'career_site')]);
        $appId = (int) $app->getKey();
        $this->assertSame('applied', $app->statusKey());
        $this->assertNotNull($app->current_stage_id);

        // 4) Review / Move pipeline → CV Screening (writes a status-history audit row).
        $flow->moveToStage($appId, $this->stageId($stages, 'CV Screening'), $this->userId, 'CV looks strong');
        $this->assertSame('in_review', Application::find($appId)->statusKey());
        $this->assertTrue(
            app('db')->table('status_histories')->where('subject_id', '=', $appId)->where('status_type', '=', 'application')->count() >= 1
        );

        // 5) Assign Recruiter.
        $flow->assignRecruiter($appId, $this->userId);
        $this->assertSame($this->userId, (int) Application::find($appId)->assigned_recruiter_id);

        // 6) Schedule Interview (creates a meeting for an interview of this application).
        $interviewId = $this->makeInterview($appId, $jobId);
        $meeting = $scheduler->schedule($interviewId, ['starts_at' => now(), 'title' => 'Technical Interview'], $this->userId);
        $this->assertNotNull($meeting->getKey());

        // 7) Run AI Interview (the state machine — the AI engine as an ATS stage).
        StateMachine::make()->start($interviewId);
        $stateId = (int) app('db')->table('interviews')->where('id', '=', $interviewId)->value('state_id');
        $this->assertTrue($stateId > 0);

        // 8) Generate Report (the Decision Engine produces an explainable decision).
        $decision = (new DecisionEngine())->decide(
            [
                ['key' => 'technical', 'label' => 'Technical', 'weight' => 60, 'max' => 5],
                ['key' => 'communication', 'label' => 'Communication', 'weight' => 40, 'max' => 5],
            ],
            ['technical' => 5, 'communication' => 4],
            70.0,
            ['interview_id' => $interviewId, 'is_ai' => true, 'summary' => 'Strong technical fit']
        );
        $this->assertTrue((float) $decision->normalized_score > 0.0);

        // 9) Move to Offer stage, then Create Offer.
        $flow->moveToStage($appId, $this->stageId($stages, 'Offer'), $this->userId);
        $offer = $offers->create($appId, ['job_title' => 'Senior Backend Engineer', 'salary_amount' => 120000], $this->userId);
        $offerId = (int) $offer->getKey();
        $this->assertSame('draft', $offer->statusKey());

        // 10) Approve → Send → Accept Offer (advances the application to hired).
        $offers->approve($offerId);
        $offers->send($offerId);
        $offers->accept($offerId);
        $this->assertSame('accepted', Offer::find($offerId)->statusKey());
        $this->assertSame('hired', Application::find($appId)->statusKey());

        // 11) Reject another candidate (move to the terminal Rejected stage).
        $app2 = $flow->apply($jobId, $this->otherUserId);
        $flow->moveToStage((int) $app2->getKey(), $this->stageId($stages, 'Rejected'), $this->userId, 'Not a fit');
        $this->assertSame('rejected', Application::find((int) $app2->getKey())->statusKey());

        // 12) Archive Job.
        $jobs->archive($jobId);
        $this->assertSame('archived', Job::find($jobId)->statusKey());
    }

    /** @param array<int,array<string,mixed>> $stages */
    private function stageId(array $stages, string $name): int
    {
        foreach ($stages as $s) {
            if ((string) $s['name'] === $name) {
                return (int) $s['id'];
            }
        }

        throw new \RuntimeException("Stage '{$name}' not found.");
    }

    /** Create an interview row for an application (the FK chain already exists). */
    private function makeInterview(int $applicationId, int $jobId): int
    {
        $now = now();

        return app('db')->table('interviews')->insertGetId([
            'uuid'                => Model::generateUuid(),
            'workspace_id'        => tenant()->id(),
            'application_id'      => $applicationId,
            'job_id'              => $jobId,
            'type_id'             => lookup_id('interview_type', 'video'),
            'mode_id'             => lookup_id('interview_mode', 'remote'),
            'interview_status_id' => status_id('interview_statuses', 'scheduled'),
            'created_by'          => $this->userId,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);
    }
};
