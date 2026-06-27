<?php

declare(strict_types=1);

namespace App\Services\Ats;

/**
 * Advanced candidate / application search (docs/53 ATS Advanced Search & Filtering).
 *
 * Two entry points:
 *  - searchApplications() — the pipeline view: filter the tenant's applications by
 *    job, status, score, source, recruiter and stage, optionally narrowed by the
 *    applicant's global candidate_profiles / candidate_skills attributes.
 *  - searchCandidates() — the talent view: filter candidate_profiles (global, NOT
 *    tenant-scoped) by experience / availability / expected salary / skills, with
 *    an optional searchable / open-to-work gate.
 *
 * Tenant safety: every query over a tenant table is constrained by workspace_id =
 * tenant()->id(). candidate_profiles and candidate_skills are global (keyed by
 * user_id, no workspace_id) and are only ever joined ON user_id — they widen the
 * predicate, never the visible row set, so they cannot leak another tenant's
 * applications.
 *
 * Supported filters
 * -----------------
 * searchApplications($filters):
 *   job_id (int) | application_status (status key → application_statuses.id) |
 *   application_status_id (int) | min_score (float) | max_score (float) |
 *   source | source_id (int) | assigned_recruiter | assigned_recruiter_id (int) |
 *   stage | current_stage_id (int) | user_id (int) |
 *   plus any candidate filter below (applied to the applicant via user_id):
 *   min_experience, max_experience, availability/availability_id,
 *   max_expected_salary, min_expected_salary, skills (int[]|string[]),
 *   is_open_to_work (bool), is_searchable (bool) |
 *   limit (int, default 200).
 *
 * searchCandidates($filters):
 *   user_id (int) | user_ids (int[]) | min_experience (float) |
 *   max_experience (float) | availability / availability_id (int) |
 *   min_expected_salary (float) | max_expected_salary (float) |
 *   expected_salary_currency_id (int) | residence_country_id (int) | city (string,
 *   partial match) | skills (int[] of skill ids, or string[] of skill names
 *   resolved within the tenant) | is_open_to_work (bool, default true) |
 *   is_searchable (bool, default true) | limit (int, default 200).
 *
 * @phpstan-type Filters array<string,mixed>
 */
final class AdvancedSearch
{
    private const DEFAULT_LIMIT = 200;

    /**
     * Search the current tenant's applications.
     *
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>> raw application rows
     */
    public function searchApplications(array $filters): array
    {
        $db = app('db');
        $workspaceId = (int) tenant()->id();

        $q = $db->table('applications')
            ->where('applications.workspace_id', '=', $workspaceId)
            ->whereNull('applications.deleted_at');

        if (isset($filters['job_id'])) {
            $q->where('applications.job_id', '=', (int) $filters['job_id']);
        }

        if (isset($filters['user_id'])) {
            $q->where('applications.user_id', '=', (int) $filters['user_id']);
        }

        $statusId = $this->resolveStatusId($filters);
        if ($statusId !== null) {
            $q->where('applications.application_status_id', '=', $statusId);
        }

        if (isset($filters['min_score'])) {
            $q->where('applications.score', '>=', (float) $filters['min_score']);
        }
        if (isset($filters['max_score'])) {
            $q->where('applications.score', '<=', (float) $filters['max_score']);
        }

        if (isset($filters['source_id'])) {
            $q->where('applications.source_id', '=', (int) $filters['source_id']);
        } elseif (isset($filters['source'])) {
            $q->where('applications.source_id', '=', (int) $filters['source']);
        }

        if (isset($filters['assigned_recruiter_id'])) {
            $q->where('applications.assigned_recruiter_id', '=', (int) $filters['assigned_recruiter_id']);
        } elseif (isset($filters['assigned_recruiter'])) {
            $q->where('applications.assigned_recruiter_id', '=', (int) $filters['assigned_recruiter']);
        }

        if (isset($filters['current_stage_id'])) {
            $q->where('applications.current_stage_id', '=', (int) $filters['current_stage_id']);
        } elseif (isset($filters['stage'])) {
            $q->where('applications.current_stage_id', '=', (int) $filters['stage']);
        }

        // Candidate-profile narrowing (global tables, joined by user_id only).
        if ($this->hasProfileFilters($filters)) {
            $q->join('candidate_profiles', 'candidate_profiles.user_id', '=', 'applications.user_id');
            $this->applyProfileFilters($q, $filters, profileGate: false);
        }

        $this->applySkillFilter($q, $filters, 'applications.user_id');

        $q->select('applications.*')
            ->orderBy('applications.id', 'desc')
            ->limit($this->limit($filters));

        return $q->get();
    }

    /**
     * Search global candidate profiles. Returns profile rows (one per candidate).
     *
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>> raw candidate_profiles rows
     */
    public function searchCandidates(array $filters): array
    {
        $db = app('db');

        $q = $db->table('candidate_profiles')
            ->whereNull('candidate_profiles.deleted_at');

        if (isset($filters['user_id'])) {
            $q->where('candidate_profiles.user_id', '=', (int) $filters['user_id']);
        }
        if (isset($filters['user_ids']) && is_array($filters['user_ids'])) {
            $q->whereIn('candidate_profiles.user_id', array_map('intval', $filters['user_ids']));
        }

        // Default to discoverable candidates unless the caller opts out explicitly.
        $this->applyProfileFilters($q, $filters, profileGate: true);

        if (isset($filters['residence_country_id'])) {
            $q->where('candidate_profiles.residence_country_id', '=', (int) $filters['residence_country_id']);
        }
        if (isset($filters['expected_salary_currency_id'])) {
            $q->where('candidate_profiles.expected_salary_currency_id', '=', (int) $filters['expected_salary_currency_id']);
        }
        if (isset($filters['city']) && $filters['city'] !== '') {
            $q->where('candidate_profiles.city', 'like', '%' . $filters['city'] . '%');
        }

        $this->applySkillFilter($q, $filters, 'candidate_profiles.user_id');

        $q->select('candidate_profiles.*')
            ->orderBy('candidate_profiles.id', 'desc')
            ->limit($this->limit($filters));

        return $q->get();
    }

    // --- Internals ---------------------------------------------------------

    /** @param array<string,mixed> $filters */
    private function resolveStatusId(array $filters): ?int
    {
        if (isset($filters['application_status_id'])) {
            return (int) $filters['application_status_id'];
        }
        if (isset($filters['application_status']) && $filters['application_status'] !== '') {
            return status_id('application_statuses', (string) $filters['application_status']);
        }

        return null;
    }

    /** @param array<string,mixed> $filters */
    private function hasProfileFilters(array $filters): bool
    {
        foreach ([
            'min_experience', 'max_experience', 'availability', 'availability_id',
            'min_expected_salary', 'max_expected_salary', 'is_open_to_work', 'is_searchable',
        ] as $key) {
            if (array_key_exists($key, $filters)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply candidate_profiles predicates. When $profileGate is true (the talent
     * view) we default is_searchable / is_open_to_work to 1 unless the caller
     * overrides; on the application view they are only applied when supplied.
     *
     * @param \App\Core\QueryBuilder $q
     * @param array<string,mixed>    $filters
     */
    private function applyProfileFilters(object $q, array $filters, bool $profileGate): void
    {
        if (isset($filters['min_experience'])) {
            $q->where('candidate_profiles.total_experience_years', '>=', (float) $filters['min_experience']);
        }
        if (isset($filters['max_experience'])) {
            $q->where('candidate_profiles.total_experience_years', '<=', (float) $filters['max_experience']);
        }

        if (isset($filters['availability_id'])) {
            $q->where('candidate_profiles.availability_id', '=', (int) $filters['availability_id']);
        } elseif (isset($filters['availability'])) {
            $q->where('candidate_profiles.availability_id', '=', (int) $filters['availability']);
        }

        if (isset($filters['min_expected_salary'])) {
            $q->where('candidate_profiles.expected_salary', '>=', (float) $filters['min_expected_salary']);
        }
        if (isset($filters['max_expected_salary'])) {
            $q->where('candidate_profiles.expected_salary', '<=', (float) $filters['max_expected_salary']);
        }

        if (array_key_exists('is_open_to_work', $filters)) {
            $q->where('candidate_profiles.is_open_to_work', '=', (int) (bool) $filters['is_open_to_work']);
        } elseif ($profileGate) {
            $q->where('candidate_profiles.is_open_to_work', '=', 1);
        }

        if (array_key_exists('is_searchable', $filters)) {
            $q->where('candidate_profiles.is_searchable', '=', (int) (bool) $filters['is_searchable']);
        } elseif ($profileGate) {
            $q->where('candidate_profiles.is_searchable', '=', 1);
        }
    }

    /**
     * Constrain results to candidates that have ALL requested skills. `skills` may
     * be an array of skill ids (int) or skill names (string, resolved within the
     * tenant + system skills). Implemented with a correlated EXISTS per skill so
     * it works on either applications or candidate_profiles without row fan-out.
     *
     * @param \App\Core\QueryBuilder $q
     * @param array<string,mixed>    $filters
     * @param string                 $userIdColumn qualified column holding the user id
     */
    private function applySkillFilter(object $q, array $filters, string $userIdColumn): void
    {
        if (! isset($filters['skills']) || ! is_array($filters['skills']) || $filters['skills'] === []) {
            return;
        }

        $skillIds = $this->resolveSkillIds($filters['skills']);
        if ($skillIds === []) {
            // Asked to match skills but none resolve → match nothing.
            $q->whereRaw('1 = 0');

            return;
        }

        // AND semantics: one EXISTS subquery per skill so all must be present.
        foreach ($skillIds as $skillId) {
            $q->whereRaw(
                'EXISTS (SELECT 1 FROM `candidate_skills` cs WHERE cs.`user_id` = '
                . $userIdColumn // a trusted, code-supplied column identifier (never user input)
                . ' AND cs.`skill_id` = ?)',
                [$skillId]
            );
        }
    }

    /**
     * Resolve a mixed list of skill ids / names to skill ids. Names are matched
     * within the current tenant's skills plus system skills (workspace_id NULL).
     *
     * @param array<int,int|string> $skills
     * @return int[]
     */
    private function resolveSkillIds(array $skills): array
    {
        $ids = [];
        $names = [];
        foreach ($skills as $skill) {
            if (is_int($skill) || (is_string($skill) && ctype_digit($skill))) {
                $ids[] = (int) $skill;
            } elseif (is_string($skill) && $skill !== '') {
                $names[] = $skill;
            }
        }

        if ($names !== []) {
            $db = app('db');
            $workspaceId = (int) tenant()->id();
            $rows = $db->table('skills')
                ->whereIn('name', $names)
                ->whereRaw('(`workspace_id` = ? OR `workspace_id` IS NULL)', [$workspaceId])
                ->whereNull('deleted_at')
                ->pluck('id');
            foreach ($rows as $id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /** @param array<string,mixed> $filters */
    private function limit(array $filters): int
    {
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : self::DEFAULT_LIMIT;

        return $limit > 0 ? $limit : self::DEFAULT_LIMIT;
    }
}
