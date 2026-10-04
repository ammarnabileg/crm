<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Presentation;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Application\WorkspacePreferences;

/**
 * Maintenance mode (Feature 14): pause the workspace for everyone except admins,
 * with an optional message and an allow-list of IPs that may still get through.
 */
final class MaintenanceController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly WorkspacePreferences $prefs,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate('settings.view')) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();

        return $this->shell->render($this->context, 'settings.maintenance', [
            'enabled' => $this->prefs->bool($ws, 'maintenance.enabled'),
            'message' => (string) $this->prefs->get($ws, 'maintenance.message', ''),
            'allowIps' => (string) $this->prefs->get($ws, 'maintenance.allow_ips', ''),
            'canManage' => $this->context->can('settings.update'),
            'yourIp' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function enable(Request $request): Response
    {
        if (($r = $this->gate('settings.update', $request)) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $this->prefs->set($ws, 'maintenance.message', trim((string) $request->input('message', '')));
        $this->prefs->set($ws, 'maintenance.allow_ips', $this->normaliseIps((string) $request->input('allow_ips', '')));
        $this->prefs->set($ws, 'maintenance.enabled', '1');

        $this->audit->record('workspaces.maintenance.enabled', [
            'workspace_id' => $ws,
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'workspace',
            'entity_id' => $ws,
            'ip' => $request->server('REMOTE_ADDR'),
        ]);
        $this->session->flash('status', 'Maintenance mode enabled.');

        return Response::redirect('/settings/maintenance');
    }

    public function disable(Request $request): Response
    {
        if (($r = $this->gate('settings.update', $request)) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $this->prefs->set($ws, 'maintenance.enabled', '0');

        $this->audit->record('workspaces.maintenance.disabled', [
            'workspace_id' => $ws,
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'workspace',
            'entity_id' => $ws,
            'ip' => $request->server('REMOTE_ADDR'),
        ]);
        $this->session->flash('status', 'Maintenance mode disabled.');

        return Response::redirect('/settings/maintenance');
    }

    /** Normalise a comma/newline/space separated IP list into a clean comma list. */
    private function normaliseIps(string $raw): string
    {
        $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
        $ips = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '' && filter_var($p, FILTER_VALIDATE_IP) !== false) {
                $ips[$p] = true;
            }
        }

        return implode(',', array_keys($ips));
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
