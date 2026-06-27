<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Model;
use App\Services\Interview\StateMachine;
use RuntimeException;
use Tests\TestCase;

/**
 * Interview State Machine transition rules (docs/51 §5, AI Interview Engine P1).
 * Exercises the engine's legality logic against the seeded `interview_states`
 * catalog — the "no illegal jumps" guarantee that underpins pause/resume/recover.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private function sm(): StateMachine
    {
        return StateMachine::make();
    }

    public function test_initial_state_is_draft(): void
    {
        $this->assertSame('draft', $this->sm()->initialStateKey());
    }

    public function test_start_only_allows_the_initial_state(): void
    {
        $sm = $this->sm();
        $this->assertTrue($sm->canTransition(null, 'draft'));
        $this->assertFalse($sm->canTransition(null, 'technical_assessment'));
    }

    public function test_linear_advance_is_allowed(): void
    {
        $sm = $this->sm();
        $this->assertTrue($sm->canTransition('draft', 'scheduled'));
        $this->assertTrue($sm->canTransition('technical_assessment', 'behavioral_assessment'));
    }

    public function test_illegal_forward_jump_is_rejected(): void
    {
        $this->assertFalse($this->sm()->canTransition('draft', 'technical_assessment'));
    }

    public function test_terminal_state_cannot_transition_out(): void
    {
        $sm = $this->sm();
        $this->assertTrue($sm->isTerminal('archived'));
        $this->assertFalse($sm->canTransition('archived', 'completed'));
    }

    public function test_pause_from_any_non_terminal_is_allowed(): void
    {
        $this->assertTrue($this->sm()->canTransition('technical_assessment', 'waiting'));
    }

    public function test_resume_from_waiting_is_allowed(): void
    {
        $this->assertTrue($this->sm()->canTransition('waiting', 'technical_assessment'));
    }

    public function test_same_state_is_rejected(): void
    {
        $this->assertFalse($this->sm()->canTransition('draft', 'draft'));
    }

    public function test_unknown_states_are_rejected(): void
    {
        $sm = $this->sm();
        $this->assertFalse($sm->canTransition('draft', 'does_not_exist'));
        $this->assertFalse($sm->canTransition('does_not_exist', 'draft'));
    }

    // --- Runtime persistence (the actual write path) -----------------------

    public function test_start_then_advance_persists_state_and_audit(): void
    {
        $sm = $this->sm();
        $interviewId = $this->makeInterview();
        $db = app('db');

        // start() moves a not-yet-started interview into the initial stage.
        $sm->start($interviewId, null);
        $stateId = (int) $db->table('interviews')->where('id', '=', $interviewId)->value('state_id');
        $draftId = (int) $sm->states()['draft']['id'];
        $this->assertSame($draftId, $stateId);

        // One audit row recording the start (from NULL → draft).
        $audit = $db->table('interview_state_transitions')
            ->where('interview_id', '=', $interviewId)->orderBy('id')->get();
        $this->assertSame(1, count($audit));
        $this->assertNull($audit[0]['from_state_id']);
        $this->assertSame($draftId, (int) $audit[0]['to_state_id']);

        // Linear advance: draft → scheduled.
        $sm->transition($interviewId, 'scheduled', null, 'next');
        $scheduledId = (int) $sm->states()['scheduled']['id'];
        $this->assertSame(
            $scheduledId,
            (int) $db->table('interviews')->where('id', '=', $interviewId)->value('state_id')
        );

        // Second audit row records draft → scheduled.
        $audit = $db->table('interview_state_transitions')
            ->where('interview_id', '=', $interviewId)->orderBy('id')->get();
        $this->assertSame(2, count($audit));
        $this->assertSame($draftId, (int) $audit[1]['from_state_id']);
        $this->assertSame($scheduledId, (int) $audit[1]['to_state_id']);
    }

    public function test_illegal_transition_throws_and_leaves_state_untouched(): void
    {
        $sm = $this->sm();
        $interviewId = $this->makeInterview();
        $db = app('db');

        $sm->start($interviewId, null); // now in draft
        $draftId = (int) $sm->states()['draft']['id'];

        $threw = false;
        try {
            $sm->transition($interviewId, 'technical_assessment'); // illegal jump
        } catch (RuntimeException $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Illegal transition must throw.');

        // State unchanged and no extra audit row was written.
        $this->assertSame(
            $draftId,
            (int) $db->table('interviews')->where('id', '=', $interviewId)->value('state_id')
        );
        $this->assertSame(
            1,
            $db->table('interview_state_transitions')->where('interview_id', '=', $interviewId)->count()
        );
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
            'title'         => 'State Machine Test Job',
            'slug'          => 'sm-test-' . substr(Model::generateUuid(), 0, 12),
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
