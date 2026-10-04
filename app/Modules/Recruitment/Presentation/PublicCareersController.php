<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Contracts\CompanyDirectory;
use HaHireAI\Core\Contracts\FileStorage;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Recruitment\Application\JobService;

/**
 * The public company careers page at /view/{slug}: a tenant's brand, "about" and
 * published openings, with no login required to browse. Each opening links to the
 * existing public job page where a visitor applies and opens a linked account.
 */
final class PublicCareersController
{
    public function __construct(
        private readonly View $view,
        private readonly CompanyDirectory $companies,
        private readonly JobService $jobs,
        private readonly FileStorage $files,
    ) {
    }

    public function show(Request $request, string $slug): Response
    {
        $company = $this->companies->findBySlug($slug);
        if ($company === null) {
            return $this->notFound();
        }

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'employment_type' => trim((string) $request->query('employment_type', '')),
            'location' => trim((string) $request->query('location', '')),
        ];

        return Response::html($this->view->page('recruitment.careers', [
            'company' => $company,
            'jobs' => $this->jobs->listPublished($company['id'], null, $filters),
            'facets' => $this->jobs->publishedFacets($company['id']),
            'filters' => $filters,
            'hasLogo' => $company['logo_file_id'] !== null,
        ], 'layouts.public', ['title' => $company['name'] . ' — Careers']));
    }

    /** Stream the company's logo publicly (read-only, scoped to its workspace). */
    public function logo(string $slug): Response
    {
        $company = $this->companies->findBySlug($slug);
        if ($company === null || $company['logo_file_id'] === null) {
            return Response::html('<h1>404</h1><p>No logo.</p>', 404);
        }

        $bytes = $this->files->read($company['id'], $company['logo_file_id']);
        $file = $this->files->find($company['id'], $company['logo_file_id']);
        if ($bytes === null || $file === null) {
            return Response::html('<h1>404</h1><p>No logo.</p>', 404);
        }

        return Response::make($bytes, 200, [
            'Content-Type' => (string) $file['mime'],
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'public, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function notFound(): Response
    {
        return Response::html($this->view->page('recruitment.careers_not_found', [], 'layouts.public', [
            'title' => 'Company not found',
        ]), 404);
    }
}
