<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Presentation;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Contracts\EntitlementResolver;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Application\WorkspacePreferences;

/**
 * White Label & Branding Center (Phase A): per-company identity applied across
 * candidate-facing surfaces. Stored as brand.* workspace preferences (no schema
 * change). Gated by the workspace.branding permission and the white_label plan
 * feature (docs/WALLET_AND_BILLING.md).
 */
final class BrandingController
{
    /** Editable text/colour brand fields: key => [label, input type]. */
    private const FIELDS = [
        'company_name' => ['Company name', 'text'],
        'legal_name' => ['Legal name', 'text'],
        'description' => ['Company description', 'textarea'],
        'color' => ['Primary colour', 'color'],
        'secondary_color' => ['Secondary colour', 'color'],
        'accent_color' => ['Accent colour', 'color'],
        'radius' => ['Corner radius (px)', 'number'],
        'career_hero_title' => ['Career page hero title', 'text'],
        'career_hero_subtitle' => ['Career page hero subtitle', 'text'],
        'footer_text' => ['Footer text', 'textarea'],
        'social_website' => ['Website URL', 'url'],
        'social_linkedin' => ['LinkedIn URL', 'url'],
        'social_twitter' => ['X / Twitter URL', 'url'],
    ];

    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly WorkspacePreferences $prefs,
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

        $values = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $values[$key] = (string) $this->prefs->get($ws, 'brand.' . $key, '');
        }

        return $this->shell->render($this->context, 'branding.index', [
            'fields' => self::FIELDS,
            'values' => $values,
            'entitled' => $this->entitled($ws),
            'hasLogo' => (string) $this->prefs->get($ws, 'brand.logo_file_id', '') !== '',
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

        foreach (array_keys(self::FIELDS) as $key) {
            $this->prefs->set($ws, 'brand.' . $key, trim((string) $request->input($key, '')));
        }

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
