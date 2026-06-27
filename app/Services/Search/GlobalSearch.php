<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Services\Ats\AdvancedSearch;

/**
 * Global search (docs/53 §Advanced Search) — the web-facing orchestrator that gives
 * the existing ATS search a single entry point + UI. It does NOT replace
 * AdvancedSearch; it composes it:
 *
 *  - keyword(q): a fast, tenant-scoped keyword sweep over Jobs (title/slug) and
 *    Applications (applicant name/email or job title) for the "I'm looking for X"
 *    box. Both halves are constrained by workspace_id = tenant()->id().
 *  - candidates(filters): delegates to the existing AdvancedSearch::searchCandidates
 *    (the talent view) and enriches each profile row with the candidate's name/email
 *    in ONE batched users query (no N+1).
 *
 * Tenant safety: jobs/applications are workspace-scoped here; candidate_profiles are
 * the global, opt-in (is_searchable) talent pool exactly as AdvancedSearch governs
 * them — this class never widens that boundary.
 */
final class GlobalSearch
{
    private const KEYWORD_LIMIT = 25;

    public function __construct(private readonly AdvancedSearch $advanced = new AdvancedSearch())
    {
    }

    /**
     * Run a combined search. Returns the three result sets the page renders.
     *
     * @param array<string,mixed> $filters candidate filters (skills/experience/…)
     * @return array{jobs:array<int,array<string,mixed>>, applications:array<int,array<string,mixed>>, candidates:array<int,array<string,mixed>>}
     */
    public function search(string $q, array $filters = []): array
    {
        $q = trim($q);

        return [
            'jobs'         => $q === '' ? [] : $this->jobs($q),
            'applications' => $q === '' ? [] : $this->applications($q),
            'candidates'   => $this->hasCandidateFilters($filters) ? $this->candidates($filters) : [],
        ];
    }

    /**
     * Tenant jobs whose title or slug matches the keyword.
     *
     * @return array<int,array<string,mixed>>
     */
    public function jobs(string $q): array
    {
        $like = '%' . $this->escapeLike($q) . '%';

        return app('db')->table('jobs')
            ->select('jobs.id', 'jobs.title', 'jobs.slug', 'jobs.job_status_id', 'jobs.created_at')
            ->where('jobs.workspace_id', '=', (int) tenant()->id())
            ->whereNull('jobs.deleted_at')
            ->whereRaw('(`jobs`.`title` LIKE ? OR `jobs`.`slug` LIKE ?)', [$like, $like])
            ->orderBy('jobs.id', 'desc')
            ->limit(self::KEYWORD_LIMIT)
            ->get();
    }

    /**
     * Tenant applications whose applicant (name/email) or job title matches the
     * keyword. Joined to users + jobs for display; strictly workspace-scoped.
     *
     * @return array<int,array<string,mixed>>
     */
    public function applications(string $q): array
    {
        $like = '%' . $this->escapeLike($q) . '%';

        return app('db')->table('applications')
            ->select(
                'applications.id',
                'applications.score',
                'applications.application_status_id',
                'applications.job_id',
                'users.name AS candidate_name',
                'users.email AS candidate_email',
                'jobs.title AS job_title',
            )
            ->join('users', 'users.id', '=', 'applications.user_id')
            ->join('jobs', 'jobs.id', '=', 'applications.job_id')
            ->where('applications.workspace_id', '=', (int) tenant()->id())
            ->whereNull('applications.deleted_at')
            ->whereRaw('(`users`.`name` LIKE ? OR `users`.`email` LIKE ? OR `jobs`.`title` LIKE ?)', [$like, $like, $like])
            ->orderBy('applications.id', 'desc')
            ->limit(self::KEYWORD_LIMIT)
            ->get();
    }

    /**
     * Talent-pool search via the existing AdvancedSearch, with each profile enriched
     * by the candidate's name/email (batched).
     *
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function candidates(array $filters): array
    {
        $profiles = $this->advanced->searchCandidates($filters);
        if ($profiles === []) {
            return [];
        }

        $userIds = array_values(array_unique(array_map(
            static fn (array $p): int => (int) ($p['user_id'] ?? 0),
            $profiles
        )));

        $users = [];
        foreach (app('db')->table('users')->whereIn('id', $userIds)->get() as $u) {
            $users[(int) $u['id']] = $u;
        }

        foreach ($profiles as &$p) {
            $u = $users[(int) ($p['user_id'] ?? 0)] ?? null;
            $p['candidate_name'] = $u['name'] ?? '';
            $p['candidate_email'] = $u['email'] ?? '';
        }
        unset($p);

        return $profiles;
    }

    /** Whether any candidate (talent) filter was supplied. */
    public function hasCandidateFilters(array $filters): bool
    {
        foreach (['skills', 'min_experience', 'max_experience', 'availability', 'availability_id',
            'min_expected_salary', 'max_expected_salary', 'city', 'is_open_to_work', 'is_searchable',
        ] as $key) {
            if (array_key_exists($key, $filters) && $filters[$key] !== '' && $filters[$key] !== []) {
                return true;
            }
        }

        return false;
    }

    /** Escape LIKE metacharacters so a user query of "50%" matches literally. */
    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
