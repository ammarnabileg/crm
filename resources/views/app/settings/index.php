<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * Workspace Settings (docs/47 EAS-9). A single tabbed screen; each tab is an
 * independent CSRF form posting to settings.update with its `section` flag, built
 * from the shared Design System (page-header, tabs, card, field + input/select/
 * switch, alert, button). Writes are gated by settings.manage — without it the
 * forms are read-only (disabled controls, no save button).
 *
 * @var array<string,mixed> $values             Effective key => value for every editable setting.
 * @var bool                $passwordConfigured  Whether an SMTP password is stored (the secret itself never reaches here).
 * @var int                 $sessionLifetime     Session lifetime (seconds) from config, shown read-only.
 */
$canManage = can('settings.manage');
$v = static fn (string $key, mixed $default = ''): mixed => $values[$key] ?? $default;

// Save button + a disabled hint, reused at the foot of each section.
$actions = static function () use ($canManage): string {
    if (! $canManage) {
        return '<p class="hint">You have read-only access to settings.</p>';
    }

    return '<div class="pt-2">' . component('button', ['label' => 'Save changes', 'type' => 'submit']) . '</div>';
};

// Opening tag for a section form (hidden CSRF + section selector).
$formOpen = static fn (string $section): string =>
    '<form method="post" action="' . e(url('settings')) . '" class="space-y-5 max-w-2xl">'
    . csrf_field()
    . '<input type="hidden" name="section" value="' . e($section) . '">';

// ---------------------------------------------------------------------------
// General
// ---------------------------------------------------------------------------
$general = $formOpen('general')
    . component('field', [
        'label' => 'Application / workspace display name', 'for' => 'general.app_name', 'name' => 'general.app_name', 'required' => true,
        'hint'  => 'Shown in the sidebar, page titles and emails.',
        'control' => component('input', ['name' => 'general.app_name', 'id' => 'general.app_name', 'value' => (string) $v('general.app_name'), 'required' => true, 'disabled' => ! $canManage]),
    ])
    . component('field', [
        'label' => 'Default timezone', 'for' => 'general.timezone', 'name' => 'general.timezone', 'required' => true,
        'hint'  => 'IANA name, e.g. Asia/Riyadh.',
        'control' => component('input', ['name' => 'general.timezone', 'id' => 'general.timezone', 'value' => (string) $v('general.timezone'), 'required' => true, 'disabled' => ! $canManage]),
    ])
    . component('field', [
        'label' => 'Date format', 'for' => 'general.date_format', 'name' => 'general.date_format', 'required' => true,
        'control' => component('select', [
            'name' => 'general.date_format', 'id' => 'general.date_format', 'disabled' => ! $canManage,
            'selected' => (string) $v('general.date_format', 'Y-m-d'),
            'options'  => ['Y-m-d' => 'Y-m-d (2026-06-27)', 'd/m/Y' => 'd/m/Y (27/06/2026)', 'm/d/Y' => 'm/d/Y (06/27/2026)', 'd M Y' => 'd M Y (27 Jun 2026)'],
        ]),
    ])
    . $actions() . '</form>';

// ---------------------------------------------------------------------------
// Company (workspace profile basics — stored via settings, not the workspaces table)
// ---------------------------------------------------------------------------
$company = $formOpen('company')
    . component('field', [
        'label' => 'Legal / company name', 'for' => 'company.legal_name', 'name' => 'company.legal_name',
        'control' => component('input', ['name' => 'company.legal_name', 'id' => 'company.legal_name', 'value' => (string) $v('company.legal_name'), 'disabled' => ! $canManage]),
    ])
    . component('field', [
        'label' => 'Address', 'for' => 'company.address', 'name' => 'company.address',
        'control' => component('textarea', ['name' => 'company.address', 'id' => 'company.address', 'rows' => 3, 'value' => (string) $v('company.address'), 'disabled' => ! $canManage]),
    ])
    . component('field', [
        'label' => 'Contact email', 'for' => 'company.contact_email', 'name' => 'company.contact_email',
        'control' => component('input', ['name' => 'company.contact_email', 'id' => 'company.contact_email', 'type' => 'email', 'value' => (string) $v('company.contact_email'), 'disabled' => ! $canManage]),
    ])
    . component('field', [
        'label' => 'Contact phone', 'for' => 'company.contact_phone', 'name' => 'company.contact_phone',
        'control' => component('input', ['name' => 'company.contact_phone', 'id' => 'company.contact_phone', 'value' => (string) $v('company.contact_phone'), 'disabled' => ! $canManage]),
    ])
    . $actions() . '</form>';

// ---------------------------------------------------------------------------
// Branding
// ---------------------------------------------------------------------------
$branding = $formOpen('branding')
    . component('field', [
        'label' => 'Primary color', 'for' => 'brand.primary_color', 'name' => 'brand.primary_color', 'required' => true,
        'hint'  => 'Hex value, e.g. #2457eb.',
        'control' => component('input', ['name' => 'brand.primary_color', 'id' => 'brand.primary_color', 'value' => (string) $v('brand.primary_color', '#2457eb'), 'required' => true, 'disabled' => ! $canManage]),
    ])
    . component('field', [
        'label' => 'Logo URL', 'for' => 'brand.logo', 'name' => 'brand.logo',
        'hint'  => 'Optional. Leave blank to use the text logo below.',
        'control' => component('input', ['name' => 'brand.logo', 'id' => 'brand.logo', 'type' => 'url', 'value' => (string) $v('brand.logo'), 'placeholder' => 'https://…/logo.svg', 'disabled' => ! $canManage]),
    ])
    . component('field', [
        'label' => 'Logo text', 'for' => 'brand.logo_text', 'name' => 'brand.logo_text',
        'hint'  => 'Wordmark shown when no logo image is set.',
        'control' => component('input', ['name' => 'brand.logo_text', 'id' => 'brand.logo_text', 'value' => (string) $v('brand.logo_text'), 'disabled' => ! $canManage]),
    ])
    . $actions() . '</form>';

// ---------------------------------------------------------------------------
// Localization
// ---------------------------------------------------------------------------
$localization = $formOpen('localization')
    . component('field', [
        'label' => 'Default locale', 'for' => 'locale', 'name' => 'locale', 'required' => true,
        'control' => component('select', [
            'name' => 'locale', 'id' => 'locale', 'disabled' => ! $canManage,
            'selected' => (string) $v('locale', 'en'),
            'options'  => ['en' => 'English (en)', 'ar' => 'العربية (ar)'],
        ]),
    ])
    . component('alert', ['variant' => 'info', 'class' => 'mb-1', 'message' => 'Arabic (ar) renders the interface right-to-left (RTL) automatically.'])
    . component('field', [
        'label' => 'Default currency', 'for' => 'currency', 'name' => 'currency', 'required' => true,
        'hint'  => 'ISO 4217 code, e.g. SAR, USD, EUR.',
        'control' => component('input', ['name' => 'currency', 'id' => 'currency', 'value' => (string) $v('currency', 'SAR'), 'required' => true, 'disabled' => ! $canManage]),
    ])
    . $actions() . '</form>';

// ---------------------------------------------------------------------------
// Email (secrets never echoed — only "configured" status is shown)
// ---------------------------------------------------------------------------
$mailEnabled = (bool) $v('mail.enabled', false);
$email = $formOpen('email')
    . component('alert', [
        'variant' => $mailEnabled ? 'info' : 'warning',
        'class'   => 'mb-1',
        'title'   => $mailEnabled ? 'Email delivery is enabled' : 'Email delivery is disabled',
        'message' => $mailEnabled
            ? 'Messages are sent via the configured transport. A copy of every message is also written to storage/logs.'
            : 'While disabled, the application does not require SMTP credentials: every outgoing message (password resets, invitations, …) is written to storage/logs instead of being sent. Turn this on once a mail transport is available.',
    ])
    . '<div>' . component('switch', ['name' => 'mail.enabled', 'label' => 'Enable email delivery', 'on' => $mailEnabled, 'disabled' => ! $canManage]) . '</div>'
    . component('field', [
        'label' => 'From address', 'for' => 'mail.from_address', 'name' => 'mail.from_address',
        'control' => component('input', ['name' => 'mail.from_address', 'id' => 'mail.from_address', 'type' => 'email', 'value' => (string) $v('mail.from_address'), 'disabled' => ! $canManage]),
    ])
    . component('field', [
        'label' => 'From name', 'for' => 'mail.from_name', 'name' => 'mail.from_name',
        'control' => component('input', ['name' => 'mail.from_name', 'id' => 'mail.from_name', 'value' => (string) $v('mail.from_name'), 'disabled' => ! $canManage]),
    ])
    . component('field', [
        'label' => 'SMTP host', 'for' => 'mail.smtp_host', 'name' => 'mail.smtp_host',
        'control' => component('input', ['name' => 'mail.smtp_host', 'id' => 'mail.smtp_host', 'value' => (string) $v('mail.smtp_host'), 'placeholder' => 'smtp.example.com', 'disabled' => ! $canManage]),
    ])
    . component('field', [
        'label' => 'SMTP port', 'for' => 'mail.smtp_port', 'name' => 'mail.smtp_port',
        'control' => component('input', ['name' => 'mail.smtp_port', 'id' => 'mail.smtp_port', 'type' => 'number', 'value' => (string) $v('mail.smtp_port', '587'), 'disabled' => ! $canManage, 'attributes' => ['min' => '1', 'max' => '65535']]),
    ])
    . component('field', [
        'label' => 'SMTP encryption', 'for' => 'mail.smtp_encryption', 'name' => 'mail.smtp_encryption',
        'control' => component('select', [
            'name' => 'mail.smtp_encryption', 'id' => 'mail.smtp_encryption', 'disabled' => ! $canManage,
            'options' => ['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL/TLS (port 465)', 'none' => 'None'],
            'selected' => (string) $v('mail.smtp_encryption', 'tls'),
        ]),
    ])
    . component('field', [
        'label' => 'SMTP username', 'for' => 'mail.smtp_username', 'name' => 'mail.smtp_username',
        'control' => component('input', ['name' => 'mail.smtp_username', 'id' => 'mail.smtp_username', 'value' => (string) $v('mail.smtp_username'), 'disabled' => ! $canManage]),
    ])
    . component('field', [
        'label' => 'SMTP password', 'for' => 'mail.smtp_password', 'name' => 'mail.smtp_password',
        'hint'  => $passwordConfigured
            ? 'A password is already configured. Leave blank to keep it; type a new one to replace it.'
            : 'No password configured. Enter one to store it securely (it is never displayed again).',
        'control' => component('input', [
            'name' => 'mail.smtp_password', 'id' => 'mail.smtp_password', 'type' => 'password',
            'placeholder' => $passwordConfigured ? '•••••••• (configured)' : '', 'disabled' => ! $canManage,
            'attributes' => ['autocomplete' => 'new-password'],
        ]),
    ])
    . $actions() . '</form>';

// ---------------------------------------------------------------------------
// Security
// ---------------------------------------------------------------------------
$security = $formOpen('security')
    . component('field', [
        'label' => 'Session lifetime', 'for' => 'security.session_lifetime',
        'hint'  => 'Configured via the environment (SESSION_LIFETIME); shown here for reference.',
        'control' => component('input', [
            'name' => 'security.session_lifetime', 'id' => 'security.session_lifetime',
            'value' => (string) (int) ($sessionLifetime / 60) . ' minutes (' . (int) $sessionLifetime . ' seconds)',
            'disabled' => true,
        ]),
    ])
    . component('field', [
        'label' => 'Minimum password length', 'for' => 'security.password_min', 'name' => 'security.password_min', 'required' => true,
        'control' => component('input', ['name' => 'security.password_min', 'id' => 'security.password_min', 'type' => 'number', 'value' => (string) $v('security.password_min', '8'), 'required' => true, 'disabled' => ! $canManage, 'attributes' => ['min' => '6', 'max' => '128']]),
    ])
    . '<div class="space-y-3">'
    . component('switch', ['name' => 'security.password_mixed_case', 'label' => 'Require mixed-case letters', 'on' => (bool) $v('security.password_mixed_case', false), 'disabled' => ! $canManage])
    . component('switch', ['name' => 'security.password_numbers', 'label' => 'Require at least one number', 'on' => (bool) $v('security.password_numbers', false), 'disabled' => ! $canManage])
    . component('switch', ['name' => 'security.two_factor_ready', 'label' => 'Two-factor authentication ready', 'on' => (bool) $v('security.two_factor_ready', false), 'disabled' => ! $canManage])
    . '</div>'
    . $actions() . '</form>';

$tabs = [
    ['label' => 'General',      'panel' => $general],
    ['label' => 'Company',      'panel' => $company],
    ['label' => 'Branding',     'panel' => $branding],
    ['label' => 'Localization', 'panel' => $localization],
    ['label' => 'Email',        'panel' => $email],
    ['label' => 'Security',     'panel' => $security],
];
?>
<?= component('page-header', [
    'title'    => 'Workspace Settings',
    'subtitle' => 'Configure how this workspace looks and behaves. Changes apply to everyone in the workspace.',
]) ?>

<?php if (! $canManage): ?>
    <?= component('alert', ['variant' => 'info', 'class' => 'mb-4', 'message' => 'You can view these settings but not change them.']) ?>
<?php endif; ?>

<?= component('card', ['slot' => component('tabs', ['id' => 'workspace-settings', 'tabs' => $tabs])]) ?>
<?php $this->endSection(); ?>
