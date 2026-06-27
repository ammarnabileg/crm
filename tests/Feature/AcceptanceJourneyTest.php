<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Model;
use App\DTOs\RegisterUserData;
use App\Models\Application;
use App\Models\Job;
use App\Models\Offer;
use App\Models\Subscription;
use App\Services\Ats\ApplicationFlow;
use App\Services\Ats\InterviewScheduler;
use App\Services\Ats\JobManager;
use App\Services\Ats\OfferManager;
use App\Services\Ats\PipelineManager;
use App\Services\Auth\RegistrationService;
use App\Services\Evaluation\DecisionEngine;
use App\Services\Interview\StateMachine;
use Tests\TestCase;

/**
 * FINAL ACCEPTANCE TEST (Testing & QA Bible) — the complete product journey end to
 * end, driven through the real services, every step asserted so a failure pinpoints
 * exactly where:
 *
 *   1) Register a new user  2) Provision their workspace (they become Owner)
 *   3) Subscribe  4) Create a job  5) Publish + pipeline  6) Apply
 *   7) Run the AI interview (state machine)  8) Generate the report (Decision Engine)
 *   9) Move the candidate through the pipeline  10) Send an offer
 *   11) Accept the offer → candidate is hired.
 *
 * Because it starts from a brand-new registration + workspace, it also exercises
 * tenant provisioning and isolation (all data is created under the new workspace).
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function test_full_product_journey_register_to_hire(): void
    {
        $registration = app(RegistrationService::class);

        // 1) + 2) Register a new user who names their workspace up front (Owner).
        $result = $registration->register(RegisterUserData::fromArray([
            'name'           => 'Acceptance Owner',
            'email'          => 'owner.acceptance@example.com',
            'password'       => 'StrongPass!234',
            'workspace_name' => 'Acceptance Co',
            'locale'         => 'en',
        ]));
        $owner = $result['user'];
        $workspace = $result['workspace'];
        $this->assertNotNull($owner->getKey());
        $this->assertNotNull($workspace);
        $this->assertSame('Acceptance Co', $workspace->name);

        // Activate the new tenant for the rest of the journey (fail-closed scope).
        tenant()->setTenant($workspace);
        $ownerId = (int) $owner->getKey();
        $this->assertSame((int) $workspace->getKey(), tenant()->id());

        // 3) Subscribe the workspace to a plan (active).
        $subscription = Subscription::create([
            'plan_id'                => (int) app('db')->table('plans')->orderBy('id')->value('id'),
            'subscription_status_id' => status_id('subscription_statuses', 'active'),
            'amount'                 => 0,
            'currency'               => 'USD',
            'starts_at'              => now(),
        ]);
        $this->assertSame('active', $subscription->statusKey());
        $this->assertSame((int) $workspace->getKey(), (int) $subscription->workspace_id); // tenant-stamped

        // A candidate signs up (no workspace of their own).
        $candidate = $registration->register(RegisterUserData::fromArray([
            'name'     => 'Acceptance Candidate',
            'email'    => 'candidate.acceptance@example.com',
            'password' => 'StrongPass!234',
        ]))['user'];
        $candidateId = (int) $candidate->getKey();

        $jobs = new JobManager();
        $pipelines = new PipelineManager();
        $flow = new ApplicationFlow($pipelines);
        $offers = new OfferManager();
        $scheduler = new InterviewScheduler();

        // 4) Create a job, then 5) publish it + seed the default pipeline.
        $job = $jobs->create(['title' => 'Product Engineer', 'openings' => 1], $ownerId);
        $jobId = (int) $job->getKey();
        $this->assertSame('draft', $job->statusKey());
        $jobs->publish($jobId);
        $this->assertSame('open', Job::find($jobId)->statusKey());
        $pipeline = $pipelines->createDefault($jobId, $ownerId);
        $stages = $pipeline->stages();
        $this->assertTrue(count($stages) >= 11);

        // 6) Candidate applies → lands on the initial stage.
        $app = $flow->apply($jobId, $candidateId, ['source_id' => lookup_id('application_source', 'career_site')]);
        $appId = (int) $app->getKey();
        $this->assertSame('applied', $app->statusKey());

        $flow->moveToStage($appId, $this->stageId($stages, 'AI Screening'), $ownerId, 'Looks promising');
        $flow->assignRecruiter($appId, $ownerId);

        // 7) Run the AI interview (the state machine drives the AI engine as an ATS stage).
        $interviewId = $this->makeInterview($appId, $jobId, $ownerId);
        $scheduler->schedule($interviewId, ['starts_at' => now(), 'title' => 'AI Interview'], $ownerId);
        StateMachine::make()->start($interviewId);
        $stateId = (int) app('db')->table('interviews')->where('id', '=', $interviewId)->value('state_id');
        $this->assertTrue($stateId > 0);

        // 8) Generate the explainable report (Decision Engine).
        $decision = (new DecisionEngine())->decide(
            [
                ['key' => 'technical', 'label' => 'Technical', 'weight' => 60, 'max' => 5],
                ['key' => 'communication', 'label' => 'Communication', 'weight' => 40, 'max' => 5],
            ],
            ['technical' => 5, 'communication' => 4],
            70.0,
            ['interview_id' => $interviewId, 'is_ai' => true, 'summary' => 'Strong fit']
        );
        $this->assertTrue((float) $decision->normalized_score > 0.0);

        // 9) Move to Offer, 10) create + approve + send the offer.
        $flow->moveToStage($appId, $this->stageId($stages, 'Offer'), $ownerId);
        $offer = $offers->create($appId, ['job_title' => 'Product Engineer', 'salary_amount' => 100000], $ownerId);
        $offerId = (int) $offer->getKey();
        $offers->approve($offerId);
        $offers->send($offerId);

        // 11) Accept the offer → the candidate is hired.
        $offers->accept($offerId);
        $this->assertSame('accepted', Offer::find($offerId)->statusKey());
        $this->assertSame('hired', Application::find($appId)->statusKey());
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

    private function makeInterview(int $applicationId, int $jobId, int $ownerId): int
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
            'created_by'          => $ownerId,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);
    }
};
