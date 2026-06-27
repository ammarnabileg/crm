<?php

declare(strict_types=1);

namespace App\Services\Ats;

use App\Models\Meeting;
use RuntimeException;

/**
 * Interview scheduling (docs/53 ATS Scheduler). Creates a `meetings` slot for an
 * interview and dispatches `interview.scheduled`; supports reschedule and cancel.
 * Timezone/availability/calendar-export refinements layer on top. Tenant-scoped.
 */
final class InterviewScheduler
{
    public function schedule(int $interviewId, array $attrs, ?int $userId = null): Meeting
    {
        if (empty($attrs['starts_at'])) {
            throw new RuntimeException('An interview meeting needs a start time.');
        }

        $meeting = Meeting::create(array_merge([
            'interview_id'      => $interviewId,
            'organizer_user_id' => $userId,
            'title'             => 'Interview',
            'type_id'           => lookup_id('meeting_mode', 'video'),
            'created_by'        => $userId,
        ], $attrs));

        AtsEvents::dispatch('interview.scheduled', [
            'interview_id' => $interviewId,
            'meeting_id'   => (int) $meeting->getKey(),
            'starts_at'    => (string) ($attrs['starts_at'] ?? ''),
        ]);

        return $meeting;
    }

    public function reschedule(int $meetingId, string $startsAt, ?string $endsAt = null): Meeting
    {
        $meeting = $this->find($meetingId);
        $update = ['starts_at' => $startsAt];
        if ($endsAt !== null) {
            $update['ends_at'] = $endsAt;
        }
        $meeting->update($update);
        AtsEvents::dispatch('interview.scheduled', ['meeting_id' => $meetingId, 'starts_at' => $startsAt, 'rescheduled' => true]);

        return $meeting;
    }

    public function cancel(int $meetingId): void
    {
        $meeting = $this->find($meetingId);
        $meeting->delete(); // soft delete
    }

    private function find(int $meetingId): Meeting
    {
        $meeting = Meeting::find($meetingId);
        if ($meeting === null) {
            throw new RuntimeException("Meeting {$meetingId} not found in this workspace.");
        }

        return $meeting;
    }
}
