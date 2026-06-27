<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Model;
use App\DTOs\RegisterUserData;
use App\Models\Application;
use App\Models\File;
use App\Models\Job;
use App\Models\Offer;
use App\Services\Ats\ApplicationFlow;
use App\Services\Ats\InterviewScheduler;
use App\Services\Ats\JobManager;
use App\Services\Ats\OfferManager;
use App\Services\Ats\PipelineManager;
use App\Services\Auth\RegistrationService;
use App\Services\Billing\BillingService;
use App\Services\Evaluation\DecisionEngine;
use App\Services\Files\FileService;
use App\Services\Interview\StateMachine;
use App\Services\Rbac\MemberDirectory;
use App\Services\Scheduling\CronRunner;
use App\Services\Scheduling\Queue;
use App\Services\Search\GlobalSearch;
use App\Models\Plan;
use Tests\TestCase;

/**
 * FINAL ACCEPTANCE — the full product journey threaded through the Phase 16 modules
 * (not just the core services), every step asserted:
 *
 *   register → workspace(Owner) → SUBSCRIBE (BillingService) → INVITE a member
 *   (MemberDirectory) → create+publish job → candidate UPLOADS a CV (FileService) →
 *   apply with that CV → GLOBAL SEARCH finds the application → AI interview
 *   (state machine) → report (Decision Engine) → offer → accept → HIRED → queue a
 *   "hired" email (Queue) and DRAIN it (CronRunner).
 *
 * This complements AcceptanceJourneyTest (which proves the core service path) by
 * proving the new integration modules work together inside the real lifecycle, all
 * under one freshly-provisioned tenant (so it also exercises isolation).
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function test_full_journey_through_phase16_modules(): void
    {
        $registration = app(RegistrationService::class);

        // 1) Register the Owner + workspace, activate the tenant.
        $result = $registration->register(RegisterUserData::fromArray([
            'name'           => 'Journey Owner',
            'email'          => 'journey.owner.' . uniqid() . '@example.com',
            'password'       => 'StrongPass!234',
            'workspace_name' => 'Journey Co',
            'locale'         => 'en',
        ]));
        $workspace = $result['workspace'];
        $ownerId = (int) $result['user']->getKey();
        tenant()->setTenant($workspace);
        $this->assertSame((int) $workspace->getKey(), tenant()->id());

        // 2) SUBSCRIBE via the Billing module (free plan → active immediately).
        $plan = Plan::create([
            'name'        => 'Journey Plan',
            'slug'        => 'journey-plan-' . uniqid(),
            'price'       => 0,
            'currency'    => 'USD',
            'interval_id' => lookup_id('billing_interval', 'monthly'),
            'trial_days'  => 0,
            'is_active'   => 1,
            'is_public'   => 1,
        ]);
        $subscription = (new BillingService())->subscribe((int) $plan->getKey());
        $this->assertSame('active', $subscription->statusKey());
        $this->assertSame((int) $plan->getKey(), (int) $subscription->getAttribute('plan_id'));

        // 3) INVITE a teammate via the Members module.
        $directory = new MemberDirectory((int) tenant()->id());
        $teammateEmail = 'teammate.' . uniqid() . '@example.com';
        $membershipId = $directory->invite('Journey Teammate', $teammateEmail, null, 'Recruiter', $ownerId);
        $this->assertTrue($membershipId > 0);
        $this->assertNotNull($directory->find($membershipId));

        // A candidate registers (no workspace of their own).
        $candidate = $registration->register(RegisterUserData::fromArray([
            'name'     => 'Journey Candidate',
            'email'    => 'journey.candidate.' . uniqid() . '@example.com',
            'password' => 'StrongPass!234',
        ]))['user'];
        $candidateId = (int) $candidate->getKey();

        // 4) Create + publish a job with the default pipeline.
        $jobs = new JobManager();
        $pipelines = new PipelineManager();
        $flow = new ApplicationFlow($pipelines);
        $job = $jobs->create(['title' => 'Platform Engineer', 'openings' => 1], $ownerId);
        $jobId = (int) $job->getKey();
        $jobs->publish($jobId);
        $this->assertSame('open', Job::find($jobId)->statusKey());
        $pipelines->createDefault($jobId, $ownerId);

        // 5) Candidate UPLOADS a CV via the Files module, then applies with it.
        $tmp = tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($tmp, "Journey Candidate\nSkills: PHP, MySQL\nExperience: 6y\n");
        $stored = (new FileService())->store([
            'name'     => 'journey-cv.txt',
            'type'     => 'text/plain',
            'tmp_name' => $tmp,
            'error'    => 0,
            'size'     => (int) filesize($tmp),
        ], $candidateId);
        $this->assertInstanceOf(File::class, $stored);
        $fileId = (int) $stored->getKey();

        $app = $flow->apply($jobId, $candidateId, [
            'source_id'      => lookup_id('application_source', 'career_site'),
            'resume_file_id' => $fileId,
        ]);
        $appId = (int) $app->getKey();
        $this->assertSame($fileId, (int) $app->getAttribute('resume_file_id'));

        // 6) GLOBAL SEARCH finds the application by the candidate's name.
        $found = (new GlobalSearch())->search('Journey Candidate');
        $appIds = array_map(static fn (array $r): int => (int) $r['id'], $found['applications']);
        $this->assertContains($appId, $appIds);

        // 7) AI interview (state machine) + 8) explainable report (Decision Engine).
        $interviewId = $this->makeInterview($appId, $jobId, $ownerId);
        (new InterviewScheduler())->schedule($interviewId, ['starts_at' => now(), 'title' => 'AI Interview'], $ownerId);
        StateMachine::make()->start($interviewId);
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

        // 9) Offer → 10) accept → HIRED.
        $offers = new OfferManager();
        $offer = $offers->create($appId, ['job_title' => 'Platform Engineer', 'salary_amount' => 120000], $ownerId);
        $offerId = (int) $offer->getKey();
        $offers->approve($offerId);
        $offers->send($offerId);
        $offers->accept($offerId);
        $this->assertSame('hired', Application::find($appId)->statusKey());

        // 11) Queue a "hired" email via the Queue module and DRAIN it via CronRunner.
        $jobRowId = (new Queue())->pushMail($teammateEmail, 'New hire', '<p>Journey Candidate was hired.</p>', (int) tenant()->id());
        $summary = (new CronRunner())->run();
        $this->assertTrue($summary['jobs_processed'] >= 1);
        $this->assertFalse(app('db')->table('queued_jobs')->where('id', '=', $jobRowId)->exists());

        // Cleanup the on-disk CV (the DB row rolls back with the transaction).
        (new FileService())->delete($fileId, $candidateId);
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
