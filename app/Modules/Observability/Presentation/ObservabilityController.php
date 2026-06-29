<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Observability\Presentation;

use HaHireAI\Core\Health\HealthChecker;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Observability\Application\BackupService;
use HaHireAI\Modules\Observability\Application\ErrorTracker;
use HaHireAI\Modules\Observability\Application\MetricsService;
use HaHireAI\Modules\Observability\Application\MonitorService;
use HaHireAI\Modules\Observability\Application\SystemDiagnostics;
use HaHireAI\Modules\Workspaces\Application\PlatformContext;
use HaHireAI\Modules\Workspaces\Presentation\PlatformShell;

/** Platform-context Observability: ops overview, diagnostics, and backups. */
final class ObservabilityController
{
    public function __construct(
        private readonly PlatformShell $shell,
        private readonly PlatformContext $context,
        private readonly AuthContext $auth,
        private readonly HealthChecker $health,
        private readonly MetricsService $metrics,
        private readonly ErrorTracker $errors,
        private readonly MonitorService $monitors,
        private readonly BackupService $backups,
        private readonly SystemDiagnostics $diagnosticsService,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function overview(): Response
    {
        if (($r = $this->gate('system.dashboard.view')) !== null) {
            return $r;
        }

        $this->monitors->tick();              // refresh alerts on view (no cron required)
        $health = $this->health->run();

        return $this->shell->render($this->context, 'admin.overview', [
            'metrics' => $this->metrics->platformSnapshot(),
            'health' => $health['status']->value,
            'alerts' => $this->monitors->openAlerts(10),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function diagnostics(Request $request): Response
    {
        if (($r = $this->gate('system.diagnostics.run')) !== null) {
            return $r;
        }

        $this->monitors->tick();
        $health = $this->health->run();

        return $this->shell->render($this->context, 'admin.diagnostics', [
            'health' => $health,
            'panels' => $this->diagnosticsService->panels([
                'https' => $request->server('HTTPS') !== null && $request->server('HTTPS') !== 'off',
                'forwarded_proto' => (string) $request->server('HTTP_X_FORWARDED_PROTO', ''),
                'host' => (string) $request->server('HTTP_HOST', ''),
            ]),
            'errors' => $this->errors->recent(15),
            'alerts' => $this->monitors->openAlerts(25),
            'backups' => $this->backups->recent(10),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    /** Machine-readable health + metrics for external monitoring. */
    public function diagnosticsJson(): Response
    {
        if (($r = $this->gate('system.observability.view')) !== null) {
            return $r;
        }

        $health = $this->health->run();

        return Response::json([
            'data' => [
                'health' => $health['status']->value,
                'probes' => $health['probes'],
                'metrics' => $this->metrics->platformSnapshot(),
                'open_alerts' => count($this->monitors->openAlerts(200)),
            ],
        ]);
    }

    public function runBackup(Request $request): Response
    {
        if (($r = $this->gate('system.diagnostics.run', $request)) !== null) {
            return $r;
        }

        $result = $this->backups->run();
        $this->audit->record('observability.backup.run', [
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'backup',
            'entity_id' => $result['id'],
            'changes' => ['status' => $result['status'], 'tables' => $result['tables']],
        ]);
        $this->session->flash('status', 'Backup ' . $result['status'] . ' (' . $result['tables'] . ' tables).');

        return Response::redirect('/diagnostics');
    }

    private function gate(string $permission, ?Request $request = null): ?Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::html('<h1>403</h1><p>Platform access requires a System Owner.</p>', 403);
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
