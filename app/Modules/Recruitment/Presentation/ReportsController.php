<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Recruitment\Application\ReportService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

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
    ) {
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
            ['metric', 'value'],
            ['published_jobs', $report['jobs']['published']],
            ['applications', $f['applications']],
            ['interviews', $f['interviews']],
            ['offers', $f['offers']],
            ['hires', $f['hires']],
        ];

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(static fn ($v): string => '"' . str_replace('"', '""', (string) $v) . '"', $row)) . "\n";
        }

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="recruitment-report.csv"',
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
