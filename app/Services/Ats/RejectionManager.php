<?php

declare(strict_types=1);

namespace App\Services\Ats;

use App\Models\Application;
use App\Models\Note;
use InvalidArgumentException;
use RuntimeException;

/**
 * Candidate rejection workflow (docs/53 ATS Rejection Management).
 *
 * Rejecting an application is a single transactional intent that:
 *   1. moves the application to the `rejected` status and stamps decided_at;
 *   2. writes a polymorphic status_histories audit row (from → rejected);
 *   3. records an internal note carrying the rejection reason (+ optional template);
 *   4. dispatches the `candidate.rejected` domain event onto the Automation bridge
 *      (which is where the actual rejection email gets enqueued later).
 *
 * The reason is a controlled key from reasons(); the email body/template is passed
 * through to the event for the notification layer but is not sent here.
 */
final class RejectionManager
{
    /**
     * Common rejection reasons (config-as-constant; no ENUM). Keyed reason → label.
     *
     * @var array<string,string>
     */
    private const REASONS = [
        'not_qualified'     => 'Not qualified for the role',
        'position_filled'   => 'Position has been filled',
        'salary_mismatch'   => 'Salary expectations mismatch',
        'failed_assessment' => 'Did not pass assessment',
        'withdrew'          => 'Candidate withdrew',
        'other'             => 'Other',
    ];

    /**
     * Reject an application.
     *
     * @param int         $applicationId the application to reject (tenant-scoped)
     * @param string      $reasonKey     one of reasons()
     * @param string|null $template      optional email template key/body for later send
     * @param int|null    $actorId       the user performing the rejection (audit + note author)
     *
     * @throws InvalidArgumentException on an unknown reason key
     * @throws RuntimeException         when the application is not in the current tenant
     */
    public function reject(int $applicationId, string $reasonKey, ?string $template = null, ?int $actorId = null): void
    {
        if (! array_key_exists($reasonKey, self::REASONS)) {
            throw new InvalidArgumentException("Unknown rejection reason [{$reasonKey}].");
        }

        $application = Application::find($applicationId);
        if ($application === null) {
            throw new RuntimeException("Application [{$applicationId}] not found in the active tenant.");
        }

        $rejectedId = status_id('application_statuses', 'rejected');
        if ($rejectedId === null) {
            throw new RuntimeException('The "rejected" application status is not configured.');
        }

        $workspaceId = (int) tenant()->id();
        $fromStatusId = $application->application_status_id !== null
            ? (int) $application->application_status_id
            : null;
        $fromStatusKey = $application->statusKey();
        $now = now();

        // 1. Move the application to rejected + stamp the decision time.
        $application->update([
            'application_status_id' => $rejectedId,
            'decided_at'            => $now,
        ]);

        // 2. Polymorphic audit row (status_histories has no soft-delete / uuid auto;
        // it is an append-only log written directly).
        app('db')->table('status_histories')->insertGetId([
            'uuid'            => Application::generateUuid(),
            'workspace_id'    => $workspaceId,
            'subject_type'    => 'App\\Models\\Application',
            'subject_id'      => $applicationId,
            'status_type'     => 'application',
            'from_status_id'  => $fromStatusId,
            'to_status_id'    => $rejectedId,
            'from_status_key' => $fromStatusKey,
            'to_status_key'   => 'rejected',
            'changed_by'      => $actorId,
            'note'            => 'Rejected: ' . self::REASONS[$reasonKey],
            'created_at'      => $now,
        ]);

        // 3. Internal note with the reason (and template reference, if supplied).
        $body = 'Application rejected. Reason: ' . self::REASONS[$reasonKey] . ' (' . $reasonKey . ').';
        if ($template !== null && $template !== '') {
            $body .= ' Template: ' . $template . '.';
        }
        Note::create([
            'notable_type' => 'App\\Models\\Application',
            'notable_id'   => $applicationId,
            'type_id'      => lookup_id('note_types', 'internal'),
            'user_id'      => $actorId,
            'body'         => $body,
            'is_pinned'    => 0,
            'is_private'   => 1,
        ]);

        // 4. Domain event → Automation bridge (best-effort; never breaks rejection).
        AtsEvents::dispatch('candidate.rejected', [
            'application_id' => $applicationId,
            'reason'         => $reasonKey,
            'template'       => $template,
            'actor_id'       => $actorId,
        ]);
    }

    /**
     * Common rejection reasons as key => label.
     *
     * @return array<string,string>
     */
    public function reasons(): array
    {
        return self::REASONS;
    }
}
