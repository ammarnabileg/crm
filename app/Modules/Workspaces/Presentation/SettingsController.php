<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Files\Application\Exceptions\FileException;
use HaHireAI\Core\Contracts\FileStorage;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Application\WorkspacePreferences;
use HaHireAI\Modules\Workspaces\Application\WorkspaceSettingsService;

/** Per-workspace settings (docs/WORKSPACE_SETTINGS.md). */
final class SettingsController
{
    /** Extended text/number settings stored as workspace preferences. */
    private const PREF_KEYS = [
        'general.date_format',
        'company.industry', 'company.website', 'company.about',
        'company.contact_email', 'company.contact_phone',
        'brand.color', 'brand.tagline', 'brand.logo_text',
        'security.session_timeout',
        'mail.from_name', 'mail.from_email', 'mail.smtp_host', 'mail.smtp_port',
        'mail.smtp_username', 'mail.encryption',
        'legal.company_legal_name', 'legal.terms_url', 'legal.privacy_url', 'legal.address',
    ];

    /** Checkbox settings (stored as '1'/'0'). */
    private const BOOL_KEYS = [
        'security.enforce_2fa',
    ];

    /** Secrets: stored but never echoed back; only overwritten when a new value is supplied. */
    private const SECRET_KEYS = [
        'mail.smtp_password',
    ];

    private const LOGO_FILE_KEY = 'brand.logo_file_id';
    private const LOGO_EXT = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly WorkspaceSettingsService $settings,
        private readonly WorkspacePreferences $prefs,
        private readonly FileStorage $files,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
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
        foreach ([...self::PREF_KEYS, ...self::BOOL_KEYS] as $key) {
            $prefs[$key] = $this->prefs->get($ws, $key, '');
        }

        return $this->shell->render($this->context, 'settings.index', [
            'workspace' => $this->context->workspace(),
            'prefs' => $prefs,
            'canUpdate' => $this->context->can('settings.update'),
            'isOwner' => (string) ($this->context->workspace()['owner_user_id'] ?? '') === (string) $this->auth->id(),
            'hasLogo' => $this->logoFileId($ws) !== null,
            'logoVersion' => substr((string) $this->logoFileId($ws), -10),
            'mailPasswordSet' => trim((string) $this->prefs->get($ws, 'mail.smtp_password', '')) !== '',
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
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

        // Extended text/number settings (branding, company, mail, legal) as prefs.
        foreach (self::PREF_KEYS as $key) {
            $this->prefs->set($ws, $key, trim((string) $request->input($this->field($key), '')));
        }

        // Checkboxes: present in POST ⇒ on.
        foreach (self::BOOL_KEYS as $key) {
            $this->prefs->set($ws, $key, $request->input($this->field($key)) !== null ? '1' : '0');
        }

        // Secrets: only overwrite when the operator typed a new value.
        foreach (self::SECRET_KEYS as $key) {
            $value = trim((string) $request->input($this->field($key), ''));
            if ($value !== '') {
                $this->prefs->set($ws, $key, $value);
            }
        }

        $logoChanged = $this->handleLogo($ws, $request);

        $this->audit->record('workspaces.settings.updated', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'workspace',
            'entity_id' => $this->context->workspaceId(),
            'ip' => $request->server('REMOTE_ADDR'),
            'changes' => [...array_keys($changes), ...($logoChanged ? ['brand.logo'] : [])],
        ]);

        $this->session->flash('status', 'Settings updated.');

        return Response::redirect('/settings');
    }

    /** Stream the current workspace's logo inline (any member may view their own brand). */
    public function logo(): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }

        $ws = (string) $this->context->workspaceId();
        $fileId = $this->logoFileId($ws);
        $bytes = $fileId !== null ? $this->files->read($ws, $fileId) : null;
        $file = $fileId !== null ? $this->files->find($ws, $fileId) : null;
        if ($bytes === null || $file === null) {
            return Response::html('<h1>404</h1><p>No logo.</p>', 404);
        }

        return Response::make($bytes, 200, [
            'Content-Type' => (string) $file['mime'],
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Apply a logo upload or removal. Returns true if the logo changed.
     * Replacing or removing deletes the previous file so no orphans accrue.
     */
    private function handleLogo(string $ws, Request $request): bool
    {
        if ($request->input('remove_logo') !== null) {
            $existing = $this->logoFileId($ws);
            if ($existing !== null) {
                $this->files->delete($ws, $existing);
                $this->prefs->set($ws, self::LOGO_FILE_KEY, '');

                return true;
            }

            return false;
        }

        $upload = $request->file('logo');
        if ($upload === null) {
            return false;
        }

        $ext = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
        if (! in_array($ext, self::LOGO_EXT, true)) {
            $this->session->flash('error', 'Logo must be a PNG, JPG, WEBP or GIF image.');

            return false;
        }

        try {
            $newId = $this->files->store($ws, $this->context->userId(), 'workspace_logo', $ws, $upload['tmp_name'], $upload['name'], $upload['size']);
        } catch (FileException $e) {
            $this->session->flash('error', $e->getMessage());

            return false;
        }

        $old = $this->logoFileId($ws);
        $this->prefs->set($ws, self::LOGO_FILE_KEY, $newId);
        if ($old !== null) {
            $this->files->delete($ws, $old);
        }

        return true;
    }

    private function logoFileId(string $ws): ?string
    {
        $id = trim((string) $this->prefs->get($ws, self::LOGO_FILE_KEY, ''));

        return $id !== '' ? $id : null;
    }

    /** Dotted preference key → HTML form field name (PHP rewrites dots to underscores). */
    private function field(string $key): string
    {
        return str_replace('.', '_', $key);
    }
}
