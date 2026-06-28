<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Recruitment\Application\Exceptions\ApplicationException;
use HaHireAI\Modules\Recruitment\Domain\ApplicationStatus;
use HaHireAI\Shared\Ulid;

/**
 * Application = the entity binding User + Job + Workspace (docs/APPLICATION_FLOW.md).
 * A user applies to a given job at most once.
 */
final class ApplicationService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly JobService $jobs,
        private readonly CandidateProfileService $candidates,
    ) {
    }

    /** Create an application (idempotent per job+user). Returns the application id. */
    public function apply(string $workspaceId, string $jobId, string $userId, ?string $coverNote = null): string
    {
        $existing = $this->connection->selectOne('SELECT id FROM applications WHERE job_id = ? AND user_id = ?', [$jobId, $userId]);
        if ($existing !== null) {
            throw new ApplicationException('You have already applied to this job.');
        }

        return $this->connection->transaction(function () use ($workspaceId, $jobId, $userId, $coverNote): string {
            $profileId = $this->candidates->getOrCreate($workspaceId, $userId);
            $stageId = $this->jobs->firstStageId($jobId);
            $id = Ulid::generate();
            $now = gmdate('Y-m-d H:i:s');

            $this->connection->statement(
                'INSERT INTO applications (id, workspace_id, job_id, user_id, candidate_profile_id, current_stage_id, status, source, cover_note, applied_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$id, $workspaceId, $jobId, $userId, $profileId, $stageId, 'applied', 'public', $coverNote, $now, $now, $now],
            );

            $this->connection->statement(
                'INSERT INTO application_stage_history (id, workspace_id, application_id, from_stage_id, to_stage_id, moved_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [Ulid::generate(), $workspaceId, $id, null, $stageId, $userId, $now],
            );

            return $id;
        });
    }

    /** @return array<string, mixed>|null */
    public function find(string $workspaceId, string $applicationId): ?array
    {
        return $this->connection->selectOne('SELECT * FROM applications WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL', [$applicationId, $workspaceId]);
    }

    /**
     * One application owned by the given candidate, joined with its job and stage.
     * Scoped to the user so a candidate can only ever see their own application.
     *
     * @return array<string, mixed>|null
     */
    public function findForCandidate(string $workspaceId, string $applicationId, string $userId): ?array
    {
        return $this->connection->selectOne(
            'SELECT a.*, j.title AS job_title, j.description AS job_description, j.location, j.employment_type,
                    s.name AS stage_name
               FROM applications a
               JOIN jobs j ON j.id = a.job_id
               LEFT JOIN pipeline_stages s ON s.id = a.current_stage_id
              WHERE a.id = ? AND a.workspace_id = ? AND a.user_id = ? AND a.deleted_at IS NULL',
            [$applicationId, $workspaceId, $userId],
        );
    }

    /**
     * Applications grouped by pipeline stage for the Kanban board.
     *
     * @return array<string, list<array<string,mixed>>>  stageId => applications
     */
    public function byStage(string $workspaceId, string $jobId): array
    {
        $rows = $this->connection->select(
            'SELECT a.id, a.status, a.current_stage_id, u.id AS user_id, u.name, u.email
               FROM applications a JOIN users u ON u.id = a.user_id
              WHERE a.workspace_id = ? AND a.job_id = ? AND a.deleted_at IS NULL
              ORDER BY a.applied_at ASC',
            [$workspaceId, $jobId],
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) ($row['current_stage_id'] ?? 'none')][] = $row;
        }

        return $grouped;
    }

    public function moveStage(string $workspaceId, string $applicationId, string $toStageId, ?string $movedBy): void
    {
        $application = $this->find($workspaceId, $applicationId);
        if ($application === null) {
            throw new ApplicationException('Application not found in this workspace.');
        }

        $stage = $this->connection->selectOne('SELECT id, type FROM pipeline_stages WHERE id = ? AND workspace_id = ?', [$toStageId, $workspaceId]);
        if ($stage === null) {
            throw new ApplicationException('Stage not found in this workspace.');
        }

        $status = match ((string) $stage['type']) {
            'hired' => 'hired',
            'rejected' => 'rejected',
            default => 'in_pipeline',
        };
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->transaction(function () use ($application, $applicationId, $toStageId, $status, $movedBy, $workspaceId, $now): void {
            $this->connection->statement(
                'UPDATE applications SET current_stage_id = ?, status = ?, updated_at = ? WHERE id = ? AND workspace_id = ?',
                [$toStageId, $status, $now, $applicationId, $workspaceId],
            );
            $this->connection->statement(
                'INSERT INTO application_stage_history (id, workspace_id, application_id, from_stage_id, to_stage_id, moved_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [Ulid::generate(), $workspaceId, $applicationId, $application['current_stage_id'] ?? null, $toStageId, $movedBy, $now],
            );
        });
    }

    /** Set the hiring-decision status (the 11-state workflow). Human decision. */
    public function setStatus(string $workspaceId, string $applicationId, string $status, ?string $actorUserId = null): void
    {
        if (! ApplicationStatus::isValid($status)) {
            throw new ApplicationException("Unknown application status [{$status}].");
        }
        $application = $this->find($workspaceId, $applicationId);
        if ($application === null) {
            throw new ApplicationException('Application not found in this workspace.');
        }

        $from = (string) ($application['status'] ?? '');
        if ($from === $status) {
            return;
        }
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->transaction(function () use ($workspaceId, $applicationId, $status, $from, $actorUserId, $now): void {
            $this->connection->statement(
                'UPDATE applications SET status = ?, updated_at = ? WHERE id = ? AND workspace_id = ?',
                [$status, $now, $applicationId, $workspaceId],
            );
            $this->connection->statement(
                'INSERT INTO application_status_history (id, workspace_id, application_id, from_status, to_status, changed_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [Ulid::generate(), $workspaceId, $applicationId, $from !== '' ? $from : null, $status, $actorUserId, $now],
            );
        });
    }

    /** @return list<array<string,mixed>> the decision-status history for an application */
    public function statusHistory(string $workspaceId, string $applicationId): array
    {
        return $this->connection->select(
            'SELECT h.from_status, h.to_status, h.created_at, u.name AS changed_by_name
               FROM application_status_history h
               LEFT JOIN users u ON u.id = h.changed_by
              WHERE h.workspace_id = ? AND h.application_id = ?
              ORDER BY h.created_at DESC, h.id DESC',
            [$workspaceId, $applicationId],
        );
    }

    /**
     * Every application in the workspace grouped by decision status — the
     * workspace-wide Kanban board (recruitment spec #7).
     *
     * @return array<string, list<array<string,mixed>>>  status => applications
     */
    public function statusBoard(string $workspaceId): array
    {
        $rows = $this->connection->select(
            'SELECT a.id, a.status, a.applied_at, u.id AS user_id, u.name, u.email, j.title AS job_title
               FROM applications a
               JOIN users u ON u.id = a.user_id
               JOIN jobs j ON j.id = a.job_id
              WHERE a.workspace_id = ? AND a.deleted_at IS NULL
              ORDER BY a.applied_at DESC',
            [$workspaceId],
        );

        $board = [];
        foreach (ApplicationStatus::values() as $status) {
            $board[$status] = [];
        }
        foreach ($rows as $row) {
            $status = ApplicationStatus::isValid((string) $row['status']) ? (string) $row['status'] : 'applied';
            $board[$status][] = $row;
        }

        return $board;
    }

    public function countForWorkspace(string $workspaceId): int
    {
        $row = $this->connection->selectOne('SELECT COUNT(*) AS c FROM applications WHERE workspace_id = ? AND deleted_at IS NULL', [$workspaceId]);

        return (int) ($row['c'] ?? 0);
    }
}
