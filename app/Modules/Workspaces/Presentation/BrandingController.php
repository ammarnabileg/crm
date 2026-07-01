<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Presentation;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Contracts\EntitlementResolver;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Workspaces\Application\BrandingService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;

/**
 * White Label & Branding Center: per-company identity applied across the app
 * shell and candidate-facing surfaces. Every field is owned by BrandingService
 * (the single source of truth) and stored as brand.* workspace preferences (no
 * schema change). Gated by the workspace.branding permission and the white_label
 * plan feature (docs/WHITE_LABEL.md, docs/WALLET_AND_BILLING.md).
 */
final class BrandingController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly BrandingService $branding,
        private readonly EntitlementResolver $entitlements,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate('workspace.branding')) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();

        return $this->shell->render($this->context, 'branding.index', [
            'fields' => $this->branding->fields(),
            'values' => $this->branding->values($ws),
            'entitled' => $this->entitled($ws),
            'hasLogo' => $this->branding->logoUrl($ws) !== null,
            'canManage' => $this->context->can('workspace.branding'),
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
        ]);
    }

    public function update(Request $request): Response
    {
        if (($r = $this->gate('workspace.branding', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();

        if (! $this->entitled($ws)) {
            $this->session->flash('error', 'White Label is a premium service. Enable it in Build Your Workspace first.');

            return Response::redirect('/billing');
        }

        $this->branding->save($ws, static fn (string $key): string => (string) $request->input($key, ''));

        $this->audit->record('workspace.branding.updated', [
            'workspace_id' => $ws,
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'workspace',
        ]);
        $this->session->flash('status', 'Branding updated.');

        return Response::redirect('/branding');
    }

    /** True when the plan includes white_label (or there is no composed plan yet). */
    private function entitled(string $ws): bool
    {
        $features = $this->entitlements->gateFeatures($ws);

        return $features === null || in_array('white_label', $features, true);
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
