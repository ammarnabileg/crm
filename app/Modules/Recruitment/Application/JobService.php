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
     * Edit a job's details + hiring configuration (spec #1). Only the provided
     * fields are changed; everything below is in the column whitelist so a single
     * generic UPDATE covers both the basic fields and the full per-job config
     * (AI screening, interview behaviour, scoring automation, avatar, deadline).
     *
     * @param  array<string, mixed>  $fields
     */
    public function update(string $workspaceId, string $jobId, array $fields): void
    {
        $allowed = [
            'title', 'description', 'location', 'employment_type', 'seniority', 'salary_min', 'salary_max', 'currency',
            // Per-job hiring configuration.
            'deadline_at', 'first_impression_enabled', 'min_first_impression_score',
            'ai_screening_enabled', 'screening_keywords', 'interview_required', 'interview_type',
            'avatar_id', 'required_skills', 'experience_min', 'experience_max', 'passing_score', 'auto_reject_score',
            'auto_advance_stage_id', 'interview_expiration_days', 'max_attempts', 'interview_duration_minutes',
            'questions_limit', 'interview_start_mode',
        ];
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

    /**
     * @param  array{q?: string, status?: string}  $filters  optional title search + status filter
     * @return list<array<string, mixed>>
     */
    public function listForWorkspace(string $workspaceId, array $filters = []): array
    {
        $sql = 'SELECT j.*, (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.deleted_at IS NULL) AS applications_count
                  FROM jobs j WHERE j.workspace_id = ? AND j.deleted_at IS NULL';
        $bindings = [$workspaceId];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $sql .= ' AND j.title LIKE ?';
            $bindings[] = '%' . $q . '%';
        }
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND j.status = ?';
            $bindings[] = $status;
        }
        $sql .= ' ORDER BY j.created_at DESC';

        return $this->connection->select($sql, $bindings);
    }

    /**
     * Clone a job into a fresh draft (title + " (Copy)"), duplicating its question
     * bank and evaluation criteria. Returns the new job id.
     */
    public function clone(string $workspaceId, string $jobId, string $createdBy): ?string
    {
        $job = $this->find($workspaceId, $jobId);
        if ($job === null) {
            return null;
        }

        $newId = $this->create(
            $workspaceId,
            $createdBy,
            (string) $job['title'] . ' (Copy)',
            (string) ($job['description'] ?? ''),
            (string) ($job['location'] ?? ''),
            (string) ($job['employment_type'] ?? ''),
            [
                'seniority' => $job['seniority'] ?? null,
                'salary_min' => $job['salary_min'] ?? null,
                'salary_max' => $job['salary_max'] ?? null,
                'currency' => $job['currency'] ?? 'USD',
            ],
        );

        $now = gmdate('Y-m-d H:i:s');
        foreach ($this->connection->select('SELECT text, position FROM job_questions WHERE job_id = ? AND workspace_id = ?', [$jobId, $workspaceId]) as $q) {
            $this->connection->statement(
                'INSERT INTO job_questions (id, workspace_id, job_id, text, position, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [Ulid::generate(), $workspaceId, $newId, $q['text'], $q['position'], $now, $now],
            );
        }
        foreach ($this->connection->select('SELECT label, weight, position FROM job_criteria WHERE job_id = ? AND workspace_id = ?', [$jobId, $workspaceId]) as $cr) {
            $this->connection->statement(
                'INSERT INTO job_criteria (id, workspace_id, job_id, label, weight, position, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [Ulid::generate(), $workspaceId, $newId, $cr['label'], $cr['weight'], $cr['position'], $now, $now],
            );
        }

        return $newId;
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
    /**
     * Published jobs for the candidate careers page, with optional search/filters.
     *
     * @param  array{q?: string, employment_type?: string, seniority?: string, location?: string}  $filters
     * @return list<array<string, mixed>>
     */
    public function listPublished(string $workspaceId, ?string $forUserId = null, array $filters = []): array
    {
        $where = "j.workspace_id = ? AND j.status = 'published' AND j.deleted_at IS NULL";
        $bindings = [(string) $forUserId, $workspaceId];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (j.title LIKE ? OR j.location LIKE ? OR j.description LIKE ?)';
            $like = '%' . $q . '%';
            array_push($bindings, $like, $like, $like);
        }
        foreach (['employment_type', 'seniority', 'location'] as $col) {
            $val = trim((string) ($filters[$col] ?? ''));
            if ($val !== '') {
                $where .= " AND j.{$col} = ?";
                $bindings[] = $val;
            }
        }

        return $this->connection->select(
            "SELECT j.*, j.public_token,
                    (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.user_id = ? AND a.deleted_at IS NULL) AS has_applied
               FROM jobs j
              WHERE {$where}
              ORDER BY j.published_at DESC, j.created_at DESC",
            $bindings,
        );
    }

    /**
     * Distinct filter values across the workspace's published jobs (for the
     * careers page filter dropdowns).
     *
     * @return array{employment_type: list<string>, seniority: list<string>, location: list<string>}
     */
    public function publishedFacets(string $workspaceId): array
    {
        $facets = ['employment_type' => [], 'seniority' => [], 'location' => []];
        foreach (array_keys($facets) as $col) {
            $rows = $this->connection->select(
                "SELECT DISTINCT j.{$col} AS v FROM jobs j
                  WHERE j.workspace_id = ? AND j.status = 'published' AND j.deleted_at IS NULL
                    AND j.{$col} IS NOT NULL AND j.{$col} <> ''
                  ORDER BY j.{$col} ASC",
                [$workspaceId],
            );
            $facets[$col] = array_map(static fn (array $r): string => (string) $r['v'], $rows);
        }

        return $facets;
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
        $base = \HaHireAI\Support\Slug::make($title) ?: 'job';

        return $base . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    }
}
