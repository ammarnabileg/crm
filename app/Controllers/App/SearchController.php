<?php

declare(strict_types=1);

namespace App\Controllers\App;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Search\GlobalSearch;

/**
 * Global Search (docs/53 §Advanced Search) — one recruiter-facing surface over the
 * existing search. A keyword (`q`) sweeps the tenant's Jobs + Applications; the
 * optional talent filters (skills / experience / salary / city) drive the existing
 * AdvancedSearch candidate query. Read-only and gated by recruitment.view; every
 * query is tenant-scoped inside GlobalSearch — this controller only marshals input.
 */
final class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(can('recruitment.view'), 403);

        $q = trim((string) $request->query('q', ''));
        $filters = $this->filters($request);

        $search = new GlobalSearch();
        $searched = $q !== '' || $search->hasCandidateFilters($filters);
        $results = $searched
            ? $search->search($q, $filters)
            : ['jobs' => [], 'applications' => [], 'candidates' => []];

        return $this->view('app.search.index', [
            'title'    => 'Search',
            'q'        => $q,
            'filters'  => $filters,
            'results'  => $results,
            'searched' => $searched,
        ]);
    }

    /**
     * Build the candidate (talent) filter set from the request — only non-blank
     * values are forwarded so AdvancedSearch applies just what was asked.
     *
     * @return array<string,mixed>
     */
    private function filters(Request $request): array
    {
        $filters = [];

        $skills = trim((string) $request->query('skills', ''));
        if ($skills !== '') {
            $filters['skills'] = array_values(array_filter(array_map('trim', explode(',', $skills)), static fn (string $s): bool => $s !== ''));
        }

        foreach (['min_experience', 'max_experience', 'min_expected_salary', 'max_expected_salary', 'city'] as $key) {
            $value = trim((string) $request->query($key, ''));
            if ($value !== '') {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }
}
