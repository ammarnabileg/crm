<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Careers\CareersService;

/**
 * Public Careers portal (docs/53 ATS) — the unauthenticated, public-facing pages
 * where anyone can browse a company's published jobs and apply.
 *
 * These routes carry NO auth and NO tenant middleware: the workspace is resolved
 * from the public slug in the URL, and CareersService filters every read to that
 * workspace's ACTIVE + published jobs only (drafts, closed jobs and other tenants'
 * jobs are never reachable — an unknown/foreign slug or job 404s). For an
 * application (a public write) the tenant context is set to the slug-resolved
 * workspace just before delegating, so the tenant-scoped Application/File rows are
 * stamped with the correct workspace_id. The POST is CSRF-protected and rate-limited
 * (see routes/web.php).
 */
final class CareersController extends Controller
{
    public function index(Request $request, string $workspace): Response
    {
        $service = new CareersService();
        $ws = $service->workspaceBySlug($workspace);
        if ($ws === null) {
            abort(404);
        }

        $keyword = trim((string) $request->query('q', ''));

        return $this->view('public.careers.index', [
            'title'     => $ws['name'] . ' — Careers',
            'workspace' => $ws,
            'jobs'      => $service->publishedJobs((int) $ws['id'], $keyword),
            'keyword'   => $keyword,
        ]);
    }

    public function show(Request $request, string $workspace, string $job): Response
    {
        $service = new CareersService();
        $ws = $service->workspaceBySlug($workspace);
        if ($ws === null) {
            abort(404);
        }

        $jobRow = $service->publishedJob((int) $ws['id'], $job);
        if ($jobRow === null) {
            abort(404);
        }

        return $this->view('public.careers.show', [
            'title'     => $jobRow['title'] . ' — ' . $ws['name'],
            'workspace' => $ws,
            'job'       => $jobRow,
        ]);
    }

    public function apply(Request $request, string $workspace, string $job): Response
    {
        $service = new CareersService();
        $ws = $service->workspaceBySlug($workspace);
        if ($ws === null) {
            abort(404);
        }

        $jobRow = $service->publishedJob((int) $ws['id'], $job);
        if ($jobRow === null) {
            abort(404);
        }

        // Validation failure throws → central handler flashes errors + old input and
        // redirects back to the job page (which re-renders them) before any write.
        $data = $this->validate($request, [
            'name'         => 'required|max:120',
            'email'        => 'required|email|max:190',
            'cover_letter' => 'nullable|max:5000',
        ]);

        // Establish the tenant from the slug-resolved workspace (NOT from applicant
        // input) so the tenant-scoped Application/File land in the right workspace.
        tenant()->setById((int) $ws['id']);

        $result = $service->submitApplication((int) $ws['id'], $jobRow, [
            'name'         => (string) $data['name'],
            'email'        => (string) $data['email'],
            'cover_letter' => $data['cover_letter'] ?? null,
        ], $request->file('cv'));

        if (isset($result['errors'])) {
            // The CV failed validation/storage — surface it on the form, keep input.
            session()->flash('errors', ['cv' => $result['errors']]);
            session()->flashInput($request->all());

            return $this->back();
        }

        // A genuine new application and a duplicate both land on the same confirmation
        // — we never reveal whether this email had already applied (no enumeration).
        return $this->view('public.careers.applied', [
            'title'     => 'Application received — ' . $ws['name'],
            'workspace' => $ws,
            'job'       => $jobRow,
            'duplicate' => (bool) ($result['duplicate'] ?? false),
        ]);
    }
}
