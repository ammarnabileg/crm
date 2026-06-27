<?php

declare(strict_types=1);

namespace App\Controllers\App;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * System / Workspace Settings (docs/47 EAS-9): one tabbed screen whose sections
 * (General, Company, Branding, Localization, Email, Security) each persist a small
 * set of namespaced keys through the per-tenant SettingsManager (settings()). The
 * store is config-backed, so the screen renders with sensible defaults before a
 * single value has been saved.
 *
 * Reads are gated by settings.view, writes by settings.manage (enforced at the
 * route layer; re-asserted here so the controller is safe in isolation/tests).
 *
 * Email degrades gracefully: when the mail.enabled toggle is off the existing
 * Mailer logs every message to storage/logs instead of sending — no SMTP
 * credentials are ever required. Secrets (the SMTP password) are written to
 * settings but NEVER echoed back to the rendered HTML; the view shows only
 * whether a password is "configured".
 */
final class SettingsController extends Controller
{
    /**
     * Effective values for every key the screen edits. Each entry maps a settings
     * key to its config()-derived default, so render works with no stored settings.
     *
     * @return array<string, mixed>
     */
    private function values(): array
    {
        $settings = settings();

        return [
            // General
            'general.app_name'    => $settings->get('general.app_name', (string) config('app.name')),
            'general.timezone'    => $settings->get('general.timezone', (string) config('app.timezone')),
            'general.date_format' => $settings->get('general.date_format', 'Y-m-d'),

            // Company (workspace profile basics — stored via settings, NOT the workspaces table)
            'company.legal_name'    => $settings->get('company.legal_name', ''),
            'company.address'       => $settings->get('company.address', ''),
            'company.contact_email' => $settings->get('company.contact_email', ''),
            'company.contact_phone' => $settings->get('company.contact_phone', ''),

            // Branding (the existing brand.* defaults already live in config/settings.php)
            'brand.primary_color' => $settings->get('brand.primary_color', '#2457eb'),
            'brand.logo'          => $settings->get('brand.logo', ''),
            'brand.logo_text'     => $settings->get('brand.logo_text', (string) config('app.name')),

            // Localization
            'locale'              => $settings->get('locale', (string) config('app.locale')),
            'currency'            => $settings->get('currency', (string) config('app.currency')),

            // Email — note: password is intentionally absent (see passwordConfigured()).
            'mail.enabled'        => (bool) $settings->get('mail.enabled', (bool) config('mail.enabled', false)),
            'mail.from_address'   => $settings->get('mail.from_address', (string) config('mail.from_address')),
            'mail.from_name'      => $settings->get('mail.from_name', (string) config('mail.from_name')),
            'mail.smtp_host'      => $settings->get('mail.smtp_host', ''),
            'mail.smtp_port'      => $settings->get('mail.smtp_port', '587'),
            'mail.smtp_username'  => $settings->get('mail.smtp_username', ''),

            // Security
            'security.password_min'        => $settings->get('security.password_min', '8'),
            'security.password_mixed_case' => (bool) $settings->get('security.password_mixed_case', false),
            'security.password_numbers'    => (bool) $settings->get('security.password_numbers', false),
            'security.two_factor_ready'    => (bool) $settings->get('security.two_factor_ready', false),
        ];
    }

    public function index(Request $request): Response
    {
        abort_unless(can('settings.view'), 403);

        return $this->view('app.settings.index', [
            'title'              => 'Workspace Settings',
            'values'             => $this->values(),
            'passwordConfigured' => settings()->has('mail.smtp_password')
                && (string) settings()->get('mail.smtp_password', '') !== '',
            'sessionLifetime'    => (int) config('session.lifetime', 7200),
        ]);
    }

    /**
     * Persist a single section. The submitted `section` field selects the rule set
     * and the keys to write; only that section's fields are touched, so each tab
     * saves independently. Thin by design — all storage/caching lives in
     * SettingsManager.
     */
    public function update(Request $request): Response
    {
        abort_unless(can('settings.manage'), 403);

        $section = (string) $request->input('section', '');
        $settings = settings();

        switch ($section) {
            case 'general':
                $data = $this->validate($request, [
                    'general.app_name'    => 'required|max:120',
                    'general.timezone'    => 'required|max:64',
                    'general.date_format' => 'required|max:32',
                ]);
                foreach (['general.app_name', 'general.timezone', 'general.date_format'] as $key) {
                    $settings->set($key, (string) $data[$key]);
                }
                break;

            case 'company':
                $data = $this->validate($request, [
                    'company.legal_name'    => 'nullable|max:160',
                    'company.address'       => 'nullable|max:500',
                    'company.contact_email' => 'nullable|email|max:190',
                    'company.contact_phone' => 'nullable|max:40',
                ]);
                foreach (['company.legal_name', 'company.address', 'company.contact_email', 'company.contact_phone'] as $key) {
                    $settings->set($key, (string) $request->input($key, ''));
                }
                break;

            case 'branding':
                $this->validate($request, [
                    'brand.primary_color' => 'required|max:32',
                    'brand.logo'          => 'nullable|url|max:300',
                    'brand.logo_text'     => 'nullable|max:60',
                ]);
                $settings->set('brand.primary_color', (string) $request->input('brand.primary_color', '#2457eb'));
                $settings->set('brand.logo', (string) $request->input('brand.logo', ''));
                $settings->set('brand.logo_text', (string) $request->input('brand.logo_text', ''));
                break;

            case 'localization':
                $data = $this->validate($request, [
                    'locale'   => 'required|in:en,ar',
                    'currency' => 'required|max:8',
                ]);
                $settings->set('locale', (string) $data['locale']);
                $settings->set('currency', (string) $data['currency']);
                break;

            case 'email':
                $this->validate($request, [
                    'mail.from_address' => 'nullable|email|max:190',
                    'mail.from_name'    => 'nullable|max:120',
                    'mail.smtp_host'    => 'nullable|max:190',
                    'mail.smtp_port'    => 'nullable|integer|between:1,65535',
                    'mail.smtp_username' => 'nullable|max:190',
                ]);
                // The toggle drives the existing Mailer's log-only fallback.
                $settings->set('mail.enabled', $request->input('mail.enabled') === '1');
                $settings->set('mail.from_address', (string) $request->input('mail.from_address', ''));
                $settings->set('mail.from_name', (string) $request->input('mail.from_name', ''));
                $settings->set('mail.smtp_host', (string) $request->input('mail.smtp_host', ''));
                $settings->set('mail.smtp_port', (string) $request->input('mail.smtp_port', ''));
                $settings->set('mail.smtp_username', (string) $request->input('mail.smtp_username', ''));

                // Secret handling: store a new password only when one was typed; an
                // empty field means "leave the configured secret untouched". The
                // password is never read back into the view.
                $password = (string) $request->input('mail.smtp_password', '');
                if ($password !== '') {
                    $settings->set('mail.smtp_password', $password);
                }
                break;

            case 'security':
                $this->validate($request, [
                    'security.password_min' => 'required|integer|between:6,128',
                ]);
                $settings->set('security.password_min', (string) $request->input('security.password_min', '8'));
                $settings->set('security.password_mixed_case', $request->input('security.password_mixed_case') === '1');
                $settings->set('security.password_numbers', $request->input('security.password_numbers') === '1');
                $settings->set('security.two_factor_ready', $request->input('security.two_factor_ready') === '1');
                break;

            default:
                $this->withError('Unknown settings section.');

                return $this->redirect(url('settings'));
        }

        $this->withSuccess('Settings saved.');

        return $this->redirect(url('settings'));
    }
}
