<?php

declare(strict_types=1);

namespace App\Services\Ats;

/**
 * Candidate / application activity timeline (docs/53 ATS Candidate Timeline).
 *
 * Merges every recruitment touch-point for a candidate (or a single application)
 * into one chronologically ascending stream: the application itself, pipeline
 * status changes (status_histories), interviews and their meetings, notes, and
 * offers. Each entry is a uniform shape:
 *   ['type' => string, 'at' => string|null, 'title' => string, 'meta' => array].
 *
 * All reads are constrained to the active tenant by workspace_id. status_histories
 * and notes are matched by their polymorphic subject (both the FQCN
 * "App\Models\Application" and the short "Application" forms are accepted so the
 * timeline is robust to either writer convention).
 */
final class CandidateTimeline
{
    private const APP_TYPES = ['App\\Models\\Application', 'Application', 'applications', 'application'];

    /**
     * Full timeline across all of a candidate's applications in this tenant.
     *
     * @return array<int,array{type:string,at:?string,title:string,meta:array<string,mixed>}>
     */
    public function forCandidate(int $userId): array
    {
        $db = app('db');
        $workspaceId = (int) tenant()->id();

        $applications = $db->table('applications')
            ->where('workspace_id', '=', $workspaceId)
            ->where('user_id', '=', $userId)
            ->whereNull('deleted_at')
            ->get();

        $applicationIds = array_map(static fn (array $r): int => (int) $r['id'], $applications);

        $entries = [];
        foreach ($applications as $app) {
            $entries[] = $this->applicationEntry($app);
        }

        foreach ($applicationIds as $applicationId) {
            $entries = array_merge($entries, $this->entriesForApplication($applicationId, $workspaceId));
        }

        // Candidate-level notes (notes attached to the user, not an application).
        $entries = array_merge($entries, $this->noteEntries($workspaceId, ['User', 'App\\Models\\User', 'users', 'user'], $userId));

        return $this->sort($entries);
    }

    /**
     * Timeline for a single application (tenant-scoped).
     *
     * @return array<int,array{type:string,at:?string,title:string,meta:array<string,mixed>}>
     */
    public function forApplication(int $applicationId): array
    {
        $db = app('db');
        $workspaceId = (int) tenant()->id();

        $app = $db->table('applications')
            ->where('workspace_id', '=', $workspaceId)
            ->where('id', '=', $applicationId)
            ->whereNull('deleted_at')
            ->first();

        if ($app === null) {
            return [];
        }

        $entries = [$this->applicationEntry($app)];
        $entries = array_merge($entries, $this->entriesForApplication($applicationId, $workspaceId));

        return $this->sort($entries);
    }

    // --- Per-application assembly ------------------------------------------

    /**
     * @return array<int,array{type:string,at:?string,title:string,meta:array<string,mixed>}>
     */
    private function entriesForApplication(int $applicationId, int $workspaceId): array
    {
        return array_merge(
            $this->statusHistoryEntries($workspaceId, $applicationId),
            $this->interviewEntries($workspaceId, $applicationId),
            $this->offerEntries($workspaceId, $applicationId),
            $this->noteEntries($workspaceId, self::APP_TYPES, $applicationId),
        );
    }

    /** @param array<string,mixed> $app */
    private function applicationEntry(array $app): array
    {
        $at = $app['applied_at'] ?? $app['created_at'] ?? null;

        return [
            'type'  => 'application',
            'at'    => $at !== null ? (string) $at : null,
            'title' => 'Application submitted',
            'meta'  => [
                'application_id'        => (int) $app['id'],
                'job_id'                => (int) $app['job_id'],
                'application_status_id' => isset($app['application_status_id']) ? (int) $app['application_status_id'] : null,
                'current_stage_id'      => isset($app['current_stage_id']) ? (int) $app['current_stage_id'] : null,
                'score'                 => $app['score'] !== null ? (float) $app['score'] : null,
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function statusHistoryEntries(int $workspaceId, int $applicationId): array
    {
        $rows = app('db')->table('status_histories')
            ->where('workspace_id', '=', $workspaceId)
            ->whereIn('subject_type', self::APP_TYPES)
            ->where('subject_id', '=', $applicationId)
            ->get();

        $entries = [];
        foreach ($rows as $row) {
            $from = $row['from_status_key'] ?? null;
            $to = $row['to_status_key'] ?? null;
            $title = $to !== null
                ? ($from !== null ? "Status changed: {$from} → {$to}" : "Status set: {$to}")
                : 'Status changed';

            $entries[] = [
                'type'  => 'status_change',
                'at'    => isset($row['created_at']) ? (string) $row['created_at'] : null,
                'title' => $title,
                'meta'  => [
                    'status_type'    => $row['status_type'] ?? null,
                    'from_status_id' => isset($row['from_status_id']) ? (int) $row['from_status_id'] : null,
                    'to_status_id'   => isset($row['to_status_id']) ? (int) $row['to_status_id'] : null,
                    'from_status_key' => $from,
                    'to_status_key'  => $to,
                    'changed_by'     => isset($row['changed_by']) ? (int) $row['changed_by'] : null,
                    'note'           => $row['note'] ?? null,
                ],
            ];
        }

        return $entries;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function interviewEntries(int $workspaceId, int $applicationId): array
    {
        $interviews = app('db')->table('interviews')
            ->where('workspace_id', '=', $workspaceId)
            ->where('application_id', '=', $applicationId)
            ->whereNull('deleted_at')
            ->get();

        $entries = [];
        foreach ($interviews as $interview) {
            $interviewId = (int) $interview['id'];
            $at = $interview['scheduled_at'] ?? $interview['created_at'] ?? null;

            $entries[] = [
                'type'  => 'interview',
                'at'    => $at !== null ? (string) $at : null,
                'title' => (string) ($interview['title'] ?? 'Interview'),
                'meta'  => [
                    'interview_id'        => $interviewId,
                    'interview_status_id' => isset($interview['interview_status_id']) ? (int) $interview['interview_status_id'] : null,
                    'state_id'            => isset($interview['state_id']) ? (int) $interview['state_id'] : null,
                    'scheduled_at'        => $interview['scheduled_at'] ?? null,
                    'started_at'          => $interview['started_at'] ?? null,
                    'ended_at'            => $interview['ended_at'] ?? null,
                ],
            ];

            // Meetings hung off the interview.
            $meetings = app('db')->table('meetings')
                ->where('workspace_id', '=', $workspaceId)
                ->where('interview_id', '=', $interviewId)
                ->whereNull('deleted_at')
                ->get();

            foreach ($meetings as $meeting) {
                $entries[] = [
                    'type'  => 'meeting',
                    'at'    => isset($meeting['starts_at']) ? (string) $meeting['starts_at'] : null,
                    'title' => (string) ($meeting['title'] ?? 'Meeting'),
                    'meta'  => [
                        'meeting_id'   => (int) $meeting['id'],
                        'interview_id' => $interviewId,
                        'starts_at'    => $meeting['starts_at'] ?? null,
                        'ends_at'      => $meeting['ends_at'] ?? null,
                        'location'     => $meeting['location'] ?? null,
                        'meeting_url'  => $meeting['meeting_url'] ?? null,
                    ],
                ];
            }
        }

        return $entries;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function offerEntries(int $workspaceId, int $applicationId): array
    {
        $offers = app('db')->table('offers')
            ->where('workspace_id', '=', $workspaceId)
            ->where('application_id', '=', $applicationId)
            ->whereNull('deleted_at')
            ->get();

        $entries = [];
        foreach ($offers as $offer) {
            $at = $offer['sent_at'] ?? $offer['created_at'] ?? null;
            $jobTitle = (string) ($offer['job_title'] ?? '');

            $entries[] = [
                'type'  => 'offer',
                'at'    => $at !== null ? (string) $at : null,
                'title' => $jobTitle !== '' ? "Offer: {$jobTitle}" : 'Offer extended',
                'meta'  => [
                    'offer_id'        => (int) $offer['id'],
                    'offer_status_id' => isset($offer['offer_status_id']) ? (int) $offer['offer_status_id'] : null,
                    'salary_amount'   => $offer['salary_amount'] ?? null,
                    'sent_at'         => $offer['sent_at'] ?? null,
                    'responded_at'    => $offer['responded_at'] ?? null,
                    'start_date'      => $offer['start_date'] ?? null,
                ],
            ];
        }

        return $entries;
    }

    /**
     * @param array<int,string> $notableTypes
     * @return array<int,array<string,mixed>>
     */
    private function noteEntries(int $workspaceId, array $notableTypes, int $notableId): array
    {
        $rows = app('db')->table('notes')
            ->where('workspace_id', '=', $workspaceId)
            ->whereIn('notable_type', $notableTypes)
            ->where('notable_id', '=', $notableId)
            ->whereNull('deleted_at')
            ->get();

        $entries = [];
        foreach ($rows as $row) {
            $body = (string) ($row['body'] ?? '');
            $entries[] = [
                'type'  => 'note',
                'at'    => isset($row['created_at']) ? (string) $row['created_at'] : null,
                'title' => 'Note added',
                'meta'  => [
                    'note_id'    => (int) $row['id'],
                    'type_id'    => isset($row['type_id']) ? (int) $row['type_id'] : null,
                    'user_id'    => isset($row['user_id']) ? (int) $row['user_id'] : null,
                    'is_pinned'  => (bool) ($row['is_pinned'] ?? false),
                    'is_private' => (bool) ($row['is_private'] ?? false),
                    'body'       => $body,
                    'excerpt'    => mb_substr($body, 0, 140),
                ],
            ];
        }

        return $entries;
    }

    /**
     * Stable ascending sort by timestamp. NULL timestamps sort last (they have no
     * fixed position in time); ties keep insertion order via a secondary index.
     *
     * @param array<int,array<string,mixed>> $entries
     * @return array<int,array<string,mixed>>
     */
    private function sort(array $entries): array
    {
        $indexed = [];
        foreach ($entries as $i => $entry) {
            $indexed[] = [$i, $entry];
        }

        usort($indexed, static function (array $a, array $b): int {
            $atA = $a[1]['at'] ?? null;
            $atB = $b[1]['at'] ?? null;

            if ($atA === $atB) {
                return $a[0] <=> $b[0];
            }
            if ($atA === null) {
                return 1;
            }
            if ($atB === null) {
                return -1;
            }

            $cmp = strcmp((string) $atA, (string) $atB);

            return $cmp !== 0 ? $cmp : ($a[0] <=> $b[0]);
        });

        return array_map(static fn (array $pair): array => $pair[1], $indexed);
    }
}
