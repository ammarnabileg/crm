<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Model;
use App\Services\Ats\CandidateTimeline;
use Tests\TestCase;

/**
 * Candidate Timeline service (docs/53 ATS Candidate Timeline). Builds an
 * application plus one of each touch-point (status change, interview + meeting,
 * note, offer) in-tx and asserts they merge into one ascending stream with the
 * uniform [type, at, title, meta] shape. All rolled back.
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

    private function service(): CandidateTimeline
    {
        return new CandidateTimeline();
    }

    /** Build job + application at a fixed early timestamp. */
    private function seed(): void
    {
        $db = app('db');
        $workspaceId = (int) tenant()->id();
        $this->userId = (int) $db->table('users')->orderBy('id')->value('id');

        $this->jobId = $db->table('jobs')->insertGetId([
            'uuid'          => Model::generateUuid(),
            'workspace_id'  => $workspaceId,
            'job_status_id' => status_id('job_statuses', 'open'),
            'title'         => 'Timeline Test Job',
            'slug'          => 'tl-' . substr(Model::generateUuid(), 0, 12),
            'created_at'    => '2026-01-01 09:00:00',
            'updated_at'    => '2026-01-01 09:00:00',
        ]);

        $this->appId = $db->table('applications')->insertGetId([
            'uuid'                  => Model::generateUuid(),
            'workspace_id'          => $workspaceId,
            'job_id'                => $this->jobId,
            'user_id'               => $this->userId,
            'application_status_id' => status_id('application_statuses', 'applied'),
            'applied_at'            => '2026-01-01 10:00:00',
            'created_at'            => '2026-01-01 10:00:00',
            'updated_at'            => '2026-01-01 10:00:00',
        ]);
    }

    private function addStatusChange(): void
    {
        app('db')->table('status_histories')->insertGetId([
            'uuid'            => Model::generateUuid(),
            'workspace_id'    => (int) tenant()->id(),
            'subject_type'    => 'App\\Models\\Application',
            'subject_id'      => $this->appId,
            'status_type'     => 'application',
            'from_status_id'  => status_id('application_statuses', 'applied'),
            'to_status_id'    => status_id('application_statuses', 'in_review'),
            'from_status_key' => 'applied',
            'to_status_key'   => 'in_review',
            'changed_by'      => $this->userId,
            'note'            => 'Moved to review',
            'created_at'      => '2026-01-02 10:00:00',
        ]);
    }

    private function addInterviewWithMeeting(): int
    {
        $db = app('db');
        $workspaceId = (int) tenant()->id();
        $interviewId = $db->table('interviews')->insertGetId([
            'uuid'                => Model::generateUuid(),
            'workspace_id'        => $workspaceId,
            'application_id'      => $this->appId,
            'job_id'              => $this->jobId,
            'type_id'             => lookup_id('interview_type', 'video'),
            'mode_id'             => lookup_id('interview_mode', 'remote'),
            'interview_status_id' => status_id('interview_statuses', 'scheduled'),
            'title'               => 'Tech Screen',
            'scheduled_at'        => '2026-01-03 14:00:00',
            'created_by'          => $this->userId,
            'created_at'          => '2026-01-03 09:00:00',
            'updated_at'          => '2026-01-03 09:00:00',
        ]);

        $db->table('meetings')->insertGetId([
            'uuid'         => Model::generateUuid(),
            'workspace_id' => $workspaceId,
            'interview_id' => $interviewId,
            'title'        => 'Tech Screen Call',
            'starts_at'    => '2026-01-03 14:00:00',
            'ends_at'      => '2026-01-03 15:00:00',
            'all_day'      => 0,
            'created_by'   => $this->userId,
            'created_at'   => '2026-01-03 09:05:00',
            'updated_at'   => '2026-01-03 09:05:00',
        ]);

        return $interviewId;
    }

    private function addNote(): void
    {
        app('db')->table('notes')->insertGetId([
            'uuid'         => Model::generateUuid(),
            'workspace_id' => (int) tenant()->id(),
            'notable_type' => 'App\\Models\\Application',
            'notable_id'   => $this->appId,
            'type_id'      => lookup_id('note_types', 'general'),
            'user_id'      => $this->userId,
            'body'         => 'Great candidate, strong fundamentals.',
            'is_pinned'    => 0,
            'is_private'   => 0,
            'created_at'   => '2026-01-04 11:00:00',
            'updated_at'   => '2026-01-04 11:00:00',
        ]);
    }

    private function addOffer(): void
    {
        app('db')->table('offers')->insertGetId([
            'uuid'            => Model::generateUuid(),
            'workspace_id'    => (int) tenant()->id(),
            'application_id'  => $this->appId,
            'offer_status_id' => status_id('offer_statuses', 'draft') ?? status_id('offer_statuses', 'sent'),
            'job_title'       => 'Senior Engineer',
            'sent_at'         => '2026-01-05 16:00:00',
            'created_by'      => $this->userId,
            'created_at'      => '2026-01-05 15:00:00',
            'updated_at'      => '2026-01-05 15:00:00',
        ]);
    }

    public function test_for_application_merges_all_entry_types(): void
    {
        $this->seed();
        $this->addStatusChange();
        $this->addInterviewWithMeeting();
        $this->addNote();
        $this->addOffer();

        $timeline = $this->service()->forApplication($this->appId);
        $types = array_map(static fn (array $e): string => $e['type'], $timeline);

        $this->assertTrue(in_array('application', $types, true));
        $this->assertTrue(in_array('status_change', $types, true));
        $this->assertTrue(in_array('interview', $types, true));
        $this->assertTrue(in_array('meeting', $types, true));
        $this->assertTrue(in_array('note', $types, true));
        $this->assertTrue(in_array('offer', $types, true));
        // application + status + interview + meeting + note + offer = 6
        $this->assertSame(6, count($timeline));
    }

    public function test_entries_are_sorted_ascending_by_at(): void
    {
        $this->seed();
        $this->addOffer();        // 2026-01-05
        $this->addStatusChange(); // 2026-01-02
        $this->addNote();         // 2026-01-04

        $timeline = $this->service()->forApplication($this->appId);

        // First entry is the application (2026-01-01 10:00); last is the offer.
        $this->assertSame('application', $timeline[0]['type']);
        $this->assertSame('offer', $timeline[count($timeline) - 1]['type']);

        $prev = '';
        foreach ($timeline as $entry) {
            $at = (string) ($entry['at'] ?? '');
            $this->assertTrue($at >= $prev);
            $prev = $at;
        }
    }

    public function test_each_entry_has_uniform_shape(): void
    {
        $this->seed();
        $this->addNote();

        foreach ($this->service()->forApplication($this->appId) as $entry) {
            $this->assertTrue(array_key_exists('type', $entry));
            $this->assertTrue(array_key_exists('at', $entry));
            $this->assertTrue(array_key_exists('title', $entry));
            $this->assertTrue(array_key_exists('meta', $entry));
            $this->assertTrue(is_array($entry['meta']));
        }
    }

    public function test_for_candidate_includes_the_application(): void
    {
        $this->seed();
        $this->addStatusChange();

        $timeline = $this->service()->forCandidate($this->userId);
        $types = array_map(static fn (array $e): string => $e['type'], $timeline);

        $this->assertTrue(in_array('application', $types, true));
        $this->assertTrue(in_array('status_change', $types, true));
    }

    public function test_for_application_unknown_id_is_empty(): void
    {
        $this->seed();
        $this->assertSame([], $this->service()->forApplication($this->appId + 999999));
    }
};
