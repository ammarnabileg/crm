<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Model;
use App\Models\CheatingScore;
use App\Services\AntiCheat\CheatingDetector;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Anti-Cheating Engine (docs/51 §16, AI Interview Engine P8).
 *
 * CONFIDENCE ONLY, NEVER PROOF. These tests assert the engine records ADVISORY
 * signals, aggregates them into a normalized 0–100 CONFIDENCE with a low/medium/
 * high BAND, and ALWAYS exposes the advisory disclaimer. They also document that
 * the score object carries 'confidence' + 'level' but NO boolean "cheated" verdict.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        tenant()->setById((int) app('db')->table('workspaces')->orderBy('id')->value('id'));
    }

    private function detector(): CheatingDetector
    {
        return new CheatingDetector();
    }

    public function test_record_signal_persists_with_resolved_weight(): void
    {
        $interviewId = $this->makeInterview();
        $signal = $this->detector()->recordSignal($interviewId, 'paste_detected', 1.0, ['field' => 'q1']);

        $this->assertNotNull($signal->getKey());
        $this->assertSame('paste_detected', $signal->signal_type);
        // Weight is resolved from config/cheating.php, not from the caller.
        $this->assertSame((float) config('cheating.signals.paste_detected.weight'), $signal->weight);

        // Round-tripped from the DB (immutable, append-only log).
        $row = app('db')->table('cheating_signals')->where('id', '=', (int) $signal->getKey())->first();
        $this->assertNotNull($row);
        $this->assertSame($interviewId, (int) $row['interview_id']);
        $this->assertSame('paste_detected', $row['signal_type']);
    }

    public function test_record_signal_rejects_unknown_type(): void
    {
        $interviewId = $this->makeInterview();

        $threw = false;
        try {
            $this->detector()->recordSignal($interviewId, 'not_a_real_signal');
        } catch (InvalidArgumentException $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'An unknown signal type must be rejected.');
    }

    public function test_compute_confidence_aggregates_into_a_0_to_100_score_with_band_and_breakdown(): void
    {
        $interviewId = $this->makeInterview();
        $detector = $this->detector();

        $detector->recordSignal($interviewId, 'tab_switch');
        $detector->recordSignal($interviewId, 'tab_switch');
        $detector->recordSignal($interviewId, 'paste_detected');

        $score = $detector->computeConfidence($interviewId);

        $this->assertInstanceOf(CheatingScore::class, $score);

        // Confidence is normalized into [0, 100].
        $this->assertTrue($score->confidence >= 0.0 && $score->confidence <= 100.0);

        // All three signals were counted.
        $this->assertSame(3, $score->signals_count);

        // Band is one of the configured CONFIDENCE bands (not a verdict).
        $this->assertTrue(in_array($score->level, ['low', 'medium', 'high'], true));

        // Breakdown explains the per-signal-type contribution.
        $breakdown = $score->breakdown;
        $this->assertTrue(is_array($breakdown));
        $this->assertTrue(array_key_exists('tab_switch', $breakdown));
        $this->assertTrue(array_key_exists('paste_detected', $breakdown));
    }

    public function test_more_and_heavier_signals_produce_higher_confidence(): void
    {
        $detector = $this->detector();

        // A single light signal.
        $lowId = $this->makeInterview();
        $detector->recordSignal($lowId, 'window_blur');
        $low = $detector->computeConfidence($lowId);

        // Many, heavier signals.
        $highId = $this->makeInterview();
        $detector->recordSignal($highId, 'multiple_faces');
        $detector->recordSignal($highId, 'multiple_voices');
        $detector->recordSignal($highId, 'devtools_opened');
        $detector->recordSignal($highId, 'ip_change');
        $high = $detector->computeConfidence($highId);

        $this->assertTrue($high->confidence > $low->confidence);
        // The heavier interview should land in a higher (or equal-but-saturated) band.
        $this->assertTrue($high->confidence >= $low->confidence);
    }

    public function test_high_confidence_lands_in_high_band(): void
    {
        $detector = $this->detector();
        $interviewId = $this->makeInterview();

        // Pile on heavy signals to push past the 'high' threshold (saturates at 100).
        foreach (['multiple_faces', 'multiple_voices', 'devtools_opened', 'ip_change', 'paste_detected'] as $type) {
            $detector->recordSignal($interviewId, $type);
            $detector->recordSignal($interviewId, $type);
        }

        $score = $detector->computeConfidence($interviewId);
        $highThreshold = (float) config('cheating.bands.high');

        $this->assertTrue($score->confidence >= $highThreshold);
        $this->assertSame('high', $score->level);
    }

    public function test_compute_confidence_is_idempotent_per_interview(): void
    {
        $interviewId = $this->makeInterview();
        $detector = $this->detector();

        $detector->recordSignal($interviewId, 'tab_switch');
        $first = $detector->computeConfidence($interviewId);

        // Recomputing after another signal updates the SAME row (UNIQUE interview_id).
        $detector->recordSignal($interviewId, 'paste_detected');
        $second = $detector->computeConfidence($interviewId);

        $this->assertSame((int) $first->getKey(), (int) $second->getKey());
        $this->assertSame(2, $second->signals_count);
        $this->assertSame(
            1,
            app('db')->table('cheating_scores')->where('interview_id', '=', $interviewId)->count()
        );
    }

    public function test_result_exposes_confidence_and_level_and_disclaimer_but_no_boolean_verdict(): void
    {
        $interviewId = $this->makeInterview();
        $detector = $this->detector();
        $detector->recordSignal($interviewId, 'rapid_answer');
        $score = $detector->computeConfidence($interviewId);

        $array = $score->toArray();

        // The score is ADVISORY: it carries a confidence + a confidence band.
        $this->assertTrue(array_key_exists('confidence', $array));
        $this->assertTrue(array_key_exists('level', $array));

        // CRITICAL (docs/51 §16): there is NO boolean "cheated"/verdict field —
        // the engine produces a confidence score only, never proof.
        $this->assertFalse(array_key_exists('cheated', $array));
        $this->assertFalse(array_key_exists('is_cheating', $array));
        $this->assertFalse(array_key_exists('verdict', $array));
        $this->assertFalse(array_key_exists('passed', $array));

        // The advisory-not-proof disclaimer is always available.
        $disclaimer = $detector->disclaimer();
        $this->assertTrue(is_string($disclaimer) && $disclaimer !== '');
    }

    /**
     * Build the minimal valid FK chain for one interview (workspace → job →
     * application → interview) and return its id. Rolled back with the test tx.
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
            'title'         => 'Anti-Cheat Test Job',
            'slug'          => 'ac-test-' . substr(Model::generateUuid(), 0, 12),
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
            'job_id'             => $jobId,
            'type_id'            => lookup_id('interview_type', 'video'),
            'mode_id'            => lookup_id('interview_mode', 'remote'),
            'interview_status_id' => status_id('interview_statuses', 'scheduled'),
            'created_by'         => $userId,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);
    }
};
