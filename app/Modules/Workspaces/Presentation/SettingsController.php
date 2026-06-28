<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Audit\Application\AuditLogger;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Application\WorkspacePreferences;
use HaHireAI\Modules\Workspaces\Application\WorkspaceSettingsService;

/** Per-workspace settings (docs/WORKSPACE_SETTINGS.md). */
final class SettingsController
{
    /** Extended settings stored as workspace preferences. */
    private const PREF_KEYS = [
        'company.industry', 'company.website', 'company.about',
        'brand.color', 'brand.tagline',
        'security.enforce_2fa', 'security.session_timeout',
        'maintenance.enabled', 'maintenance.message',
    ];

    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly WorkspaceSettingsService $settings,
        private readonly WorkspacePreferences $prefs,
        private readonly Session $session,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can('settings.view')) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }

        $ws = (string) $this->context->workspaceId();
        $prefs = [];
        foreach (self::PREF_KEYS as $key) {
            $prefs[$key] = $this->prefs->get($ws, $key, '');
        }

        return $this->shell->render($this->context, 'settings.index', [
            'workspace' => $this->context->workspace(),
            'prefs' => $prefs,
            'canUpdate' => $this->context->can('settings.update'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function update(Request $request): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can('settings.update') || ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }

        $ws = (string) $this->context->workspaceId();
        $changes = $this->settings->update($ws, [
            'name' => $request->input('name'),
            'timezone' => $request->input('timezone'),
            'locale' => $request->input('locale'),
            'currency' => $request->input('currency'),
        ]);

        // Extended settings (branding, company, security, maintenance) as prefs.
        foreach (self::PREF_KEYS as $key) {
            if (str_starts_with($key, 'maintenance.enabled') || str_starts_with($key, 'security.enforce_2fa')) {
                $this->prefs->set($ws, $key, $request->input($this->field($key)) !== null ? '1' : '0');

                continue;
            }
            $this->prefs->set($ws, $key, trim((string) $request->input($this->field($key), '')));
        }

        if ($changes !== []) {
            $this->audit->record('workspaces.settings.updated', [
                'workspace_id' => $this->context->workspaceId(),
                'actor_user_id' => $this->context->userId(),
                'entity_type' => 'workspace',
                'entity_id' => $this->context->workspaceId(),
                'ip' => $request->server('REMOTE_ADDR'),
                'changes' => array_keys($changes),
            ]);
        }

        $this->session->flash('status', 'Settings updated.');

        return Response::redirect('/settings');
    }

    /** Dotted preference key → HTML form field name (PHP rewrites dots to underscores). */
    private function field(string $key): string
    {
        return str_replace('.', '_', $key);
    }
}
