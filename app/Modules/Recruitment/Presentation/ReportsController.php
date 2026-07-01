<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Recruitment\Application\FirstImpressionAnalyticsService;
use HaHireAI\Modules\Recruitment\Application\ReportService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;
use HaHireAI\Shared\XlsxWriter;

/** Workspace recruitment analytics — the hiring funnel + activity. */
final class ReportsController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly ReportService $reports,
        private readonly View $view,
        private readonly Session $session,
        private readonly FirstImpressionAnalyticsService $fiAnalytics,
    ) {
    }

    /**
     * First Impression Analytics — the credit-saving dashboard: applicants,
     * passed/filtered, average score, score distribution, top & missing skills,
     * common weaknesses, the funnel, conversion rate, and AI credits saved.
     */
    public function firstImpression(Request $request): Response
    {
        if (($r = $this->gate('report.view')) !== null) {
            return $r;
        }

        $jobId = trim((string) $request->query('job_id', '')) ?: null;

        return $this->shell->render($this->context, 'reports.first_impression', [
            // NB: not keyed 'data' — View::render()'s extract(EXTR_SKIP) would skip
            // it (its own $data parameter shadows the key).
            'fi' => $this->fiAnalytics->summary((string) $this->context->workspaceId(), $jobId),
        ]);
    }

    public function index(): Response
    {
        if (($r = $this->gate('report.view')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'reports.index', [
            'report' => $this->reports->workspaceReport((string) $this->context->workspaceId()),
            'canExport' => $this->context->can('report.export'),
        ]);
    }

    /** A printable report (use the browser's "Save as PDF") — spec #18. */
    public function print(): Response
    {
        if (($r = $this->gate('report.view')) !== null) {
            return $r;
        }

        return Response::html($this->view->page('reports.print', [
            'report' => $this->reports->workspaceReport((string) $this->context->workspaceId()),
            'workspace' => $this->context->workspace(),
        ], 'layouts.print', ['title' => 'Recruitment report']));
    }

    /** CSV export of the funnel (report.export). */
    public function export(): Response
    {
        if (($r = $this->gate('report.export')) !== null) {
            return $r;
        }

        $report = $this->reports->workspaceReport((string) $this->context->workspaceId());
        $f = $report['funnel'];
        $rows = [
            ['Metric', 'Value'],
            ['Published jobs', (int) $report['jobs']['published']],
            ['Applications', (int) $f['applications']],
            ['Interviews', (int) $f['interviews']],
            ['Offers', (int) $f['offers']],
            ['Hires', (int) $f['hires']],
        ];

        return Response::make(XlsxWriter::fromRows($rows, 'Recruitment Report'), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="recruitment-report.xlsx"',
        ]);
    }

    private function gate(string $permission, ?Request $request = null): ?Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can($permission)) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }
        if ($request !== null && ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }

        return null;
    }
}
