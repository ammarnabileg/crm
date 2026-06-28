<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Platform\Presentation;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Platform\Application\PlatformSettings;
use HaHireAI\Modules\Workspaces\Application\PlatformContext;
use HaHireAI\Modules\Workspaces\Presentation\PlatformShell;

/** Platform-wide settings (System Owner): support contact + platform identity. */
final class PlatformSettingsController
{
    private const KEYS = ['platform.name', 'support.email', 'support.url', 'support.phone', 'support.message'];

    public function __construct(
        private readonly PlatformShell $shell,
        private readonly PlatformContext $context,
        private readonly AuthContext $auth,
        private readonly PlatformSettings $settings,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate()) !== null) {
            return $r;
        }

        $values = [];
        foreach (self::KEYS as $key) {
            $values[$key] = (string) $this->settings->get($key, '');
        }

        return $this->shell->render($this->context, 'admin.settings', [
            'values' => $values,
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function update(Request $request): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        foreach (self::KEYS as $key) {
            $field = str_replace('.', '_', $key);
            $this->settings->set($key, trim((string) $request->input($field, '')));
        }

        $this->audit->record('platform.settings.updated', [
            'actor_user_id' => $this->auth->id(),
            'entity_type' => 'platform_settings',
            'changes' => self::KEYS,
        ]);
        $this->session->flash('status', 'Platform settings saved.');

        return Response::redirect('/admin/settings');
    }

    private function gate(?Request $request = null): ?Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::html('<h1>403</h1><p>Platform access requires a System Owner.</p>', 403);
        }
        if (! $this->context->can('system.settings.manage')) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }
        if ($request !== null && ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }

        return null;
    }
}
