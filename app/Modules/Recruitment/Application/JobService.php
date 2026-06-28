<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Jobs: requisition + public posting + pipeline. Each job is its own workspace
 * (docs/FEATURE_SPECIFICATIONS/Recruitment.md, STATE_DIAGRAMS §2).
 */
final class JobService
{
    private const DEFAULT_STAGES = ['Applied', 'Screening', 'Interview', 'Offer', 'Hired', 'Rejected'];

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @param array{seniority?: ?string, salary_min?: ?int, salary_max?: ?int, currency?: string} $extra */
    public function create(string $workspaceId, string $createdBy, string $title, ?string $description = null, ?string $location = null, ?string $employmentType = null, array $extra = []): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->statement(
            'INSERT INTO jobs (id, workspace_id, title, seniority, salary_min, salary_max, currency, slug, description, status, employment_type, location, public_token, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, $workspaceId, $title,
                $extra['seniority'] ?? null, $extra['salary_min'] ?? null, $extra['salary_max'] ?? null, $extra['currency'] ?? 'USD',
                $this->slug($title), $description, 'draft', $employmentType, $location,
                strtolower(bin2hex(random_bytes(8))), $createdBy, $now, $now,
            ],
        );

        return $id;
    }

    public function publish(string $workspaceId, string $jobId): void
    {
        $this->connection->statement(
            "UPDATE jobs SET status = 'published', published_at = ?, updated_at = ? WHERE id = ? AND workspace_id = ?",
            [gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s'), $jobId, $workspaceId],
        );

        if ($this->stagesForJob($jobId) === []) {
            $this->createDefaultStages($workspaceId, $jobId);
        }
    }

    public function setStatus(string $workspaceId, string $jobId, string $status): void
    {
        $this->connection->statement(
            'UPDATE jobs SET status = ?, updated_at = ? WHERE id = ? AND workspace_id = ?',
            [$status, gmdate('Y-m-d H:i:s'), $jobId, $workspaceId],
        );
    }

    /**
     * Edit a job's details (spec #1). Only the provided fields are changed.
     *
     * @param  array<string, mixed>  $fields  title, description, location, employment_type, seniority, salary_min, salary_max, currency
     */
    public function update(string $workspaceId, string $jobId, array $fields): void
    {
        $allowed = ['title', 'description', 'location', 'employment_type', 'seniority', 'salary_min', 'salary_max', 'currency'];
        $set = [];
        $bindings = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $fields)) {
                $set[] = "{$col} = ?";
                $bindings[] = $fields[$col];
            }
        }
        if ($set === []) {
            return;
        }
        $set[] = 'updated_at = ?';
        $bindings[] = gmdate('Y-m-d H:i:s');
        $bindings[] = $jobId;
        $bindings[] = $workspaceId;

        $this->connection->statement(
            'UPDATE jobs SET ' . implode(', ', $set) . ' WHERE id = ? AND workspace_id = ?',
            $bindings,
        );
    }

    /** Archive (soft-delete) a job within this workspace (spec #1). */
    public function archive(string $workspaceId, string $jobId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            "UPDATE jobs SET status = 'archived', deleted_at = ?, updated_at = ? WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL",
            [$now, $now, $jobId, $workspaceId],
        );
    }

    /** @return list<array<string, mixed>> */
    public function listForWorkspace(string $workspaceId): array
    {
        return $this->connection->select(
            'SELECT j.*, (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.deleted_at IS NULL) AS applications_count
               FROM jobs j WHERE j.workspace_id = ? AND j.deleted_at IS NULL ORDER BY j.created_at DESC',
            [$workspaceId],
        );
    }

    /** @return array<string, mixed>|null */
    public function find(string $workspaceId, string $jobId): ?array
    {
        return $this->connection->selectOne('SELECT * FROM jobs WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL', [$jobId, $workspaceId]);
    }

    /**
     * Published (open) jobs in a workspace — the candidate-facing "Available Jobs"
     * list. Includes whether the given user has already applied.
     *
     * @return list<array<string, mixed>>
     */
    public function listPublished(string $workspaceId, ?string $forUserId = null): array
    {
        return $this->connection->select(
            "SELECT j.*, j.public_token,
                    (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.user_id = ? AND a.deleted_at IS NULL) AS has_applied
               FROM jobs j
              WHERE j.workspace_id = ? AND j.status = 'published' AND j.deleted_at IS NULL
              ORDER BY j.published_at DESC, j.created_at DESC",
            [(string) $forUserId, $workspaceId],
        );
    }

    /** @return array<string, mixed>|null a published job, for the public page */
    public function findPublished(string $token): ?array
    {
        return $this->connection->selectOne("SELECT * FROM jobs WHERE public_token = ? AND status = 'published' AND deleted_at IS NULL", [$token]);
    }

    /** @return list<array<string, mixed>> */
    public function stagesForJob(string $jobId): array
    {
        return $this->connection->select('SELECT * FROM pipeline_stages WHERE job_id = ? ORDER BY position ASC', [$jobId]);
    }

    public function firstStageId(string $jobId): ?string
    {
        $row = $this->connection->selectOne('SELECT id FROM pipeline_stages WHERE job_id = ? ORDER BY position ASC LIMIT 1', [$jobId]);

        return $row !== null ? (string) $row['id'] : null;
    }

    private function createDefaultStages(string $workspaceId, string $jobId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $position = 0;

        foreach (self::DEFAULT_STAGES as $name) {
            $type = match ($name) {
                'Hired' => 'hired',
                'Rejected' => 'rejected',
                default => 'stage',
            };
            $this->connection->statement(
                'INSERT INTO pipeline_stages (id, workspace_id, job_id, name, position, type, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [Ulid::generate(), $workspaceId, $jobId, $name, $position++, $type, $now, $now],
            );
        }
    }

    private function slug(string $title): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)) ?? '', '-') ?: 'job';

        return $base . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    }
}
