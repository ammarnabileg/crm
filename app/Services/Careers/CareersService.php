<?php

declare(strict_types=1);

namespace App\Services\Careers;

use App\Core\Database;
use App\Core\Hash;
use App\Core\Model;
use App\Models\Application;
use App\Services\Ats\ApplicationFlow;
use App\Services\Cv\CvService;
use App\Services\Files\FileService;
use App\Services\Files\FileStorage;

/**
 * Public Careers portal data layer (docs/53 ATS).
 *
 * The careers pages are UNAUTHENTICATED and have no active tenant, so this service
 * is INTENTIONALLY not tenant-scoped: every read resolves the workspace from the
 * URL slug and then filters explicitly by that `workspace_id`. Only an ACTIVE
 * workspace's OPEN + published, non-deleted jobs are ever exposed — drafts,
 * paused/closed jobs and other tenants' jobs are never reachable, even by URL
 * tampering (an unknown/foreign slug or job simply resolves to null → 404).
 *
 * Writes (an application) DO need a tenant: the controller sets the tenant context
 * to the slug-resolved workspace before calling submitApplication, so the
 * tenant-scoped Application/File rows are stamped with the correct workspace_id.
 * The workspace is derived from the public slug, never from applicant input, so
 * isolation holds: an applicant can only ever apply to the workspace named in the
 * URL, to one of its published jobs.
 */
final class CareersService
{
    private Database $db;

    public function __construct(?Database $db = null, private readonly ?FileService $files = null)
    {
        $this->db = $db ?? app('db');
    }

    /**
     * Resolve an ACTIVE (or trialing) workspace by its public slug. Suspended,
     * inactive, unknown or soft-deleted workspaces resolve to null so their
     * careers page 404s rather than leaking jobs.
     *
     * @return array<string,mixed>|null
     */
    public function workspaceBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $statuses = array_values(array_filter([
            status_id('workspace_statuses', 'active'),
            status_id('workspace_statuses', 'trial'),
        ], static fn ($v): bool => $v !== null));

        if ($statuses === []) {
            return null;
        }

        $row = $this->db->table('workspaces')
            ->select('id', 'name', 'slug', 'logo', 'locale')
            ->where('slug', '=', $slug)
            ->whereIn('workspace_status_id', $statuses)
            ->whereNull('deleted_at')
            ->first();

        return $row ?: null;
    }

    /**
     * Published, public jobs for a workspace (status `open` + a `published_at`
     * timestamp), newest first. An optional keyword narrows by title/summary.
     *
     * @return array<int, array<string,mixed>>
     */
    public function publishedJobs(int $workspaceId, ?string $keyword = null): array
    {
        $openStatus = status_id('job_statuses', 'open');
        if ($openStatus === null) {
            return [];
        }

        $query = $this->db->table('jobs')
            ->select(
                'jobs.id',
                'jobs.uuid',
                'jobs.title',
                'jobs.slug',
                'jobs.summary',
                'jobs.is_remote',
                'jobs.openings',
                'jobs.published_at',
                'jobs.salary_min',
                'jobs.salary_max',
                'jobs.is_salary_public',
                'departments.name AS department',
                'employment_type.label AS employment_type',
                'currencies.code AS currency_code',
            )
            ->leftJoin('departments', 'departments.id', '=', 'jobs.department_id')
            ->leftJoin('lookup_values AS employment_type', 'employment_type.id', '=', 'jobs.employment_type_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'jobs.currency_id')
            ->where('jobs.workspace_id', '=', $workspaceId)
            ->where('jobs.job_status_id', '=', (int) $openStatus)
            ->whereNotNull('jobs.published_at')
            ->whereNull('jobs.deleted_at');

        $keyword = trim((string) $keyword);
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->whereRaw('(jobs.title LIKE ? OR jobs.summary LIKE ?)', [$like, $like]);
        }

        $rows = $query->orderBy('jobs.published_at', 'desc')
            ->orderBy('jobs.id', 'desc')
            ->limit(200)
            ->get();

        return array_map([$this, 'presentJob'], $rows);
    }

    /**
     * A single published job by slug within a workspace, with its full description.
     * Returns null when the slug is unknown/unpublished/foreign (→ 404).
     *
     * @return array<string,mixed>|null
     */
    public function publishedJob(int $workspaceId, string $jobSlug): ?array
    {
        $jobSlug = trim($jobSlug);
        $openStatus = status_id('job_statuses', 'open');
        if ($jobSlug === '' || $openStatus === null) {
            return null;
        }

        $row = $this->db->table('jobs')
            ->select(
                'jobs.id',
                'jobs.uuid',
                'jobs.title',
                'jobs.slug',
                'jobs.summary',
                'jobs.description',
                'jobs.is_remote',
                'jobs.openings',
                'jobs.published_at',
                'jobs.salary_min',
                'jobs.salary_max',
                'jobs.is_salary_public',
                'departments.name AS department',
                'employment_type.label AS employment_type',
                'experience_level.label AS experience_level',
                'currencies.code AS currency_code',
            )
            ->leftJoin('departments', 'departments.id', '=', 'jobs.department_id')
            ->leftJoin('lookup_values AS employment_type', 'employment_type.id', '=', 'jobs.employment_type_id')
            ->leftJoin('lookup_values AS experience_level', 'experience_level.id', '=', 'jobs.experience_level_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'jobs.currency_id')
            ->where('jobs.workspace_id', '=', $workspaceId)
            ->where('jobs.slug', '=', $jobSlug)
            ->where('jobs.job_status_id', '=', (int) $openStatus)
            ->whereNotNull('jobs.published_at')
            ->whereNull('jobs.deleted_at')
            ->first();

        if ($row === null) {
            return null;
        }

        $job = $this->presentJob($row);
        $job['description'] = (string) ($row['description'] ?? '');
        $job['experience_level'] = $row['experience_level'] !== null ? (string) $row['experience_level'] : null;

        return $job;
    }

    /**
     * Submit a public application to a published job. The caller MUST have already
     * resolved the workspace + job from the slug AND set the active tenant to that
     * workspace, so the tenant-scoped Application/File rows are stamped correctly.
     *
     * Find-or-create the applicant by email (the single users table — a candidate
     * is just a User), optionally store a CV, then create the application through
     * the existing ApplicationFlow (initial pipeline stage + events). Re-applying
     * to the same job is a no-op, reported as a duplicate (no error, no spam).
     *
     * @param array<string,mixed>      $job   Row from publishedJob()
     * @param array{name:string,email:string,cover_letter:?string} $input
     * @param array<string,mixed>|null $cvFile A $_FILES entry, or null
     * @return array{application?:Application, duplicate?:bool, errors?:array<int,string>}
     */
    public function submitApplication(int $workspaceId, array $job, array $input, ?array $cvFile = null): array
    {
        $jobId = (int) $job['id'];
        $userId = $this->resolveApplicantId((string) $input['name'], (string) $input['email'], $workspaceId);

        // Already applied? Treat as success-without-duplicate (idempotent, anti-spam).
        $already = Application::query()
            ->where('job_id', '=', $jobId)
            ->where('user_id', '=', $userId)
            ->exists();
        if ($already) {
            return ['duplicate' => true];
        }

        $resumeFileId = null;
        if ($this->hasUpload($cvFile)) {
            $stored = ($this->files ?? new FileService())->store($cvFile, $userId);
            if (is_array($stored)) {
                return ['errors' => $stored['errors'] ?? ['The CV could not be uploaded.']];
            }
            $resumeFileId = (int) $stored->getKey();

            // Read the CV so the candidate's profile + skills are populated. Strictly
            // best-effort — it never throws and never blocks the application.
            try {
                $abs = (new FileStorage())->path((string) $stored->getAttribute('path'));
                if ($abs !== null) {
                    (new CvService())->parseFile(
                        $userId,
                        $abs,
                        (string) $stored->getAttribute('original_name'),
                        (int) $workspaceId,
                        $resumeFileId,
                    );
                }
            } catch (\Throwable) {
                // CV reading is enrichment only — a failure must not affect applying.
            }
        }

        $coverLetter = trim((string) ($input['cover_letter'] ?? ''));
        $attrs = [
            'source_id'    => lookup_id('application_source', 'career_site'),
            'cover_letter' => $coverLetter !== '' ? $coverLetter : null,
        ];
        if ($resumeFileId !== null) {
            $attrs['resume_file_id'] = $resumeFileId;
        }

        $application = (new ApplicationFlow())->apply($jobId, $userId, $attrs);

        return ['application' => $application];
    }

    /**
     * Find a user by email, or create a minimal candidate account (random password;
     * they own it via the reset flow). Mirrors MemberDirectory::resolveUserId so the
     * single-users-table rule holds — a candidate is a User, not a separate type.
     */
    private function resolveApplicantId(string $name, string $email, int $workspaceId): int
    {
        $email = strtolower(trim($email));
        $existing = $this->db->table('users')->where('email', '=', $email)->first();
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $locale = (string) ($this->db->table('workspaces')->where('id', '=', $workspaceId)->value('locale') ?: 'en');

        return (int) $this->db->table('users')->insertGetId([
            'uuid'           => Model::generateUuid(),
            'name'           => $name !== '' ? $name : 'Candidate',
            'email'          => $email,
            'password'       => \App\Core\Hash::make(bin2hex(random_bytes(16))),
            'locale'         => $locale,
            'user_status_id' => lookup_id('user_status', 'active'),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    /**
     * @param array<string,mixed>|null $file
     */
    private function hasUpload(?array $file): bool
    {
        return $file !== null
            && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && ($file['tmp_name'] ?? '') !== ''
            && (int) ($file['size'] ?? 0) > 0;
    }

    /**
     * Shape a raw job row for the views, applying the salary-visibility rule.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function presentJob(array $row): array
    {
        $salaryPublic = (bool) ($row['is_salary_public'] ?? false);

        return [
            'id'              => (int) $row['id'],
            'uuid'            => (string) ($row['uuid'] ?? ''),
            'title'           => (string) $row['title'],
            'slug'            => (string) $row['slug'],
            'summary'         => (string) ($row['summary'] ?? ''),
            'is_remote'       => (bool) ($row['is_remote'] ?? false),
            'openings'        => (int) ($row['openings'] ?? 1),
            'department'      => $row['department'] !== null ? (string) $row['department'] : null,
            'employment_type' => $row['employment_type'] !== null ? (string) $row['employment_type'] : null,
            'published_at'    => (string) ($row['published_at'] ?? ''),
            'salary'          => $salaryPublic ? $this->formatSalary($row) : null,
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function formatSalary(array $row): ?string
    {
        $min = $row['salary_min'] !== null ? (float) $row['salary_min'] : null;
        $max = $row['salary_max'] !== null ? (float) $row['salary_max'] : null;
        if ($min === null && $max === null) {
            return null;
        }

        $code = (string) ($row['currency_code'] ?? '');
        $fmt = static fn (float $n): string => number_format($n);

        if ($min !== null && $max !== null) {
            return trim($code . ' ' . $fmt($min) . ' – ' . $fmt($max));
        }

        return trim($code . ' ' . $fmt($min ?? $max));
    }
}
