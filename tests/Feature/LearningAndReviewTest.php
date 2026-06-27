<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Model;
use App\Services\AI\AiFeatures;
use App\Services\Evaluation\DecisionEngine;
use App\Services\Learning\LearningEngine;
use App\Services\Review\HumanReview;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * AI Interview Engine P10 — Learning Engine + Human-in-the-Loop + AI Feature Flags
 * (docs/51 §18, §20, Human-in-the-Loop). Exercises human oversight of a Decision
 * Engine output (approve/edit/reject + history), the captured learning signal and its
 * tenant-scoped aggregate, and AI capability flag resolution.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        tenant()->setById((int) app('db')->table('workspaces')->orderBy('id')->value('id'));
    }

    private function review(): HumanReview
    {
        return new HumanReview();
    }

    private function learning(): LearningEngine
    {
        return new LearningEngine();
    }

    private function features(): AiFeatures
    {
        return new AiFeatures();
    }

    /** @return array<int, array<string,mixed>> A small weighted rubric. */
    private function rubric(): array
    {
        return [
            ['key' => 'technical_depth', 'label' => 'Technical Depth', 'weight' => 60, 'max' => 5],
            ['key' => 'communication',   'label' => 'Communication',   'weight' => 40, 'max' => 5],
        ];
    }

    /** Create a persisted decision (via the real Decision Engine) and return its id. */
    private function makeDecision(): int
    {
        $interviewId = $this->makeInterview();
        $record = (new DecisionEngine())->decide($this->rubric(), [
            'technical_depth' => 5,
            'communication'   => 4,
        ], 70.0, ['interview_id' => $interviewId, 'is_ai' => true, 'summary' => 'AI draft']);

        return (int) $record->getKey();
    }

    // --- Human-in-the-Loop reviews -----------------------------------------

    public function test_approve_records_review_and_sets_status(): void
    {
        $db = app('db');
        $decisionId = $this->makeDecision();

        $review = $this->review()->review($decisionId, 'approve', null, 'Looks correct');

        $this->assertSame('approve', (string) $review->action);
        $this->assertSame('Looks correct', (string) $review->reason);

        $row = $db->table('decision_records')->where('id', '=', $decisionId)->first();
        $this->assertSame('approve', (string) $row['review_status']);
        $this->assertNotNull($row['reviewed_at']);

        // One review row was logged for the decision.
        $this->assertSame(1, $db->table('decision_reviews')
            ->where('decision_id', '=', $decisionId)->count());
    }

    public function test_edit_applies_allowed_changes_to_the_decision(): void
    {
        $db = app('db');
        $decisionId = $this->makeDecision();
        $recId = lookup_id('recommendation', 'no');
        $userId = (int) $db->table('users')->orderBy('id')->value('id');

        $review = $this->review()->review($decisionId, 'edit', $userId, 'Human override', [
            'normalized_score'  => 42.5,
            'passed'            => false,
            'recommendation_id' => $recId,
            'summary'           => 'Edited by reviewer',
            'is_ai'             => true, // not allowed → must be ignored
        ]);

        $row = $db->table('decision_records')->where('id', '=', $decisionId)->first();
        $this->assertSame('edit', (string) $row['review_status']);
        $this->assertSame(42.5, (float) $row['normalized_score']);
        $this->assertSame(0, (int) $row['passed']);
        $this->assertSame($recId, (int) $row['recommendation_id']);
        $this->assertSame('Edited by reviewer', (string) $row['summary']);
        $this->assertSame($userId, (int) $row['reviewed_by']);

        // The disallowed field was NOT recorded in the review's changes.
        $this->assertSame(['normalized_score', 'passed', 'recommendation_id', 'summary'],
            array_keys((array) $review->changes));
    }

    public function test_invalid_action_throws(): void
    {
        $decisionId = $this->makeDecision();

        $threw = false;
        try {
            $this->review()->review($decisionId, 'sabotage');
        } catch (InvalidArgumentException) {
            $threw = true;
        }
        $this->assertTrue($threw);
    }

    public function test_history_returns_reviews_in_order(): void
    {
        $decisionId = $this->makeDecision();
        $hr = $this->review();

        $hr->review($decisionId, 'request_changes', null, 'Need more detail');
        $hr->review($decisionId, 'approve', null, 'Now fine');

        $history = $hr->history($decisionId);
        $this->assertSame(2, count($history));
        $this->assertSame('request_changes', (string) $history[0]['action']);
        $this->assertSame('approve', (string) $history[1]['action']);
    }

    // --- Learning Engine ----------------------------------------------------

    public function test_record_feedback_and_stats_aggregate(): void
    {
        $decisionId = $this->makeDecision();
        $le = $this->learning();

        $fb = $le->recordFeedback([
            'decision_id' => $decisionId,
            'source'      => 'human',
            'rating'      => 4.0,
            'correction'  => ['recommendation' => 'no'],
            'notes'       => 'Overestimated',
        ]);
        $this->assertSame('human', (string) $fb->source);
        $this->assertSame(['recommendation' => 'no'], (array) $fb->correction);

        $le->recordFeedback(['decision_id' => $decisionId, 'source' => 'outcome', 'rating' => 2.0]);
        $le->recordFeedback(['decision_id' => $decisionId, 'source' => 'system']);

        $forDecision = $le->forDecision($decisionId);
        $this->assertSame(3, count($forDecision));

        $stats = $le->stats();
        $this->assertSame(3, $stats['feedback_count']);
        $this->assertSame(3.0, $stats['avg_rating']); // (4 + 2) / 2 scored ratings
        $this->assertSame(1, $stats['by_source']['human']);
        $this->assertSame(1, $stats['by_source']['outcome']);
        $this->assertSame(1, $stats['by_source']['system']);
    }

    public function test_invalid_feedback_source_throws(): void
    {
        $threw = false;
        try {
            $this->learning()->recordFeedback(['source' => 'telepathy']);
        } catch (InvalidArgumentException) {
            $threw = true;
        }
        $this->assertTrue($threw);
    }

    // --- AI feature flags ---------------------------------------------------

    public function test_flags_resolve_from_config(): void
    {
        $f = $this->features();
        $this->assertTrue($f->enabled('memory_engine'));
        $this->assertFalse($f->enabled('voice_analysis'));
    }

    public function test_unknown_flag_defaults_to_false(): void
    {
        $this->assertFalse($this->features()->enabled('does_not_exist'));
    }

    public function test_all_returns_the_resolved_map(): void
    {
        $all = $this->features()->all();
        $this->assertTrue(array_key_exists('memory_engine', $all));
        $this->assertTrue($all['memory_engine']);
        $this->assertFalse($all['video_analysis']);
        $this->assertSame(9, count($all));
    }

    /**
     * Build the minimal valid FK chain for one interview (workspace → job →
     * application → interview) and return its id. Rolled back with the test tx.
     * (Copied from StateMachineTest so this test is self-contained.)
     */
    private function makeInterview(): int
    {
        $db = app('db');
        $workspaceId = (int) $db->table('workspaces')->orderBy('id')->value('id');
        $userId = (int) $db->table('users')->orderBy('id')->value('id');
        tenant()->setById($workspaceId);
        $now = now();

        $jobId = $db->table('jobs')->insertGetId([
            'uuid'          => Model::generateUuid(),
            'workspace_id'  => $workspaceId,
            'job_status_id' => status_id('job_statuses', 'open'),
            'title'         => 'Learning/Review Test Job',
            'slug'          => 'lr-test-' . substr(Model::generateUuid(), 0, 12),
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        $applicationId = $db->table('applications')->insertGetId([
            'uuid'                  => Model::generateUuid(),
            'workspace_id'          => $workspaceId,
            'job_id'                => $jobId,
            'user_id'               => $userId,
            'application_status_id' => status_id('application_statuses', 'applied'),
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);

        return $db->table('interviews')->insertGetId([
            'uuid'                => Model::generateUuid(),
            'workspace_id'        => $workspaceId,
            'application_id'      => $applicationId,
            'job_id'              => $jobId,
            'type_id'             => lookup_id('interview_type', 'video'),
            'mode_id'             => lookup_id('interview_mode', 'remote'),
            'interview_status_id' => status_id('interview_statuses', 'scheduled'),
            'created_by'          => $userId,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);
    }
};
