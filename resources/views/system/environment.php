<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * Environment Editor. Edits a whitelist of safe .env keys; secrets are shown
 * masked and read-only. Booleans render as selects, everything else as text.
 */
$labels = [
    'APP_NAME'            => 'Application name',
    'APP_URL'             => 'Application URL',
    'APP_DEBUG'           => 'Debug mode',
    'APP_LOCALE'          => 'Default language',
    'APP_FALLBACK_LOCALE' => 'Fallback language',
    'APP_TIMEZONE'        => 'Timezone',
    'APP_CURRENCY'        => 'Currency code',
    'ASSET_VERSION'       => 'Asset version',
    'MAIL_ENABLED'        => 'Email sending',
    'MAIL_FROM_ADDRESS'   => 'From address',
    'MAIL_FROM_NAME'      => 'From name',
    'SESSION_SECURE'      => 'Secure session cookie',
];
$hints = [
    'APP_URL'        => 'Full base URL, e.g. https://app.example.com',
    'APP_DEBUG'      => 'Keep disabled in production; never expose stack traces to users.',
    'APP_TIMEZONE'   => 'IANA timezone, e.g. Asia/Riyadh.',
    'APP_CURRENCY'   => '3-letter ISO code, e.g. SAR, USD, AED.',
    'ASSET_VERSION'  => 'Bump to bust browser caches after deploying new assets.',
    'MAIL_FROM_NAME' => 'Display name shown on outgoing emails.',
    'SESSION_SECURE' => 'Send the session cookie only over HTTPS. Enable when served over TLS.',
];
$groupTitles = [
    'app'      => 'Application',
    'mail'     => 'Mail',
    'security' => 'Security',
];

/** Render one whitelisted field (select for booleans, text otherwise). */
$renderField = function (string $key) use ($labels, $hints, $values, $booleans, $locales) {
    $label = $labels[$key] ?? $key;
    $value = old($key, $values[$key] ?? '');
    $isBool = in_array($key, $booleans, true);
    $isLocale = in_array($key, ['APP_LOCALE', 'APP_FALLBACK_LOCALE'], true);
    ?>
    <div>
        <label class="label" for="<?= e($key) ?>"><?= e($label) ?></label>
        <?php if ($isBool): ?>
            <?php $on = in_array(strtolower((string) $value), ['true', '1', 'on'], true); ?>
            <select class="input" id="<?= e($key) ?>" name="<?= e($key) ?>">
                <option value="false" <?= $on ? '' : 'selected' ?>>Disabled</option>
                <option value="true" <?= $on ? 'selected' : '' ?>>Enabled</option>
            </select>
        <?php elseif ($isLocale): ?>
            <select class="input" id="<?= e($key) ?>" name="<?= e($key) ?>">
                <?php foreach ($locales as $code => $name): ?>
                    <option value="<?= e($code) ?>" <?= (string) $value === (string) $code ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <input class="input" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($value) ?>"
                   <?= $key === 'APP_NAME' ? 'required' : '' ?>>
        <?php endif; ?>
        <?php if (! empty($hints[$key])): ?>
            <p class="mt-1 text-xs text-slate-500"><?= e($hints[$key]) ?></p>
        <?php endif; ?>
        <code class="mt-1 block text-[11px] text-slate-400"><?= e($key) ?></code>
    </div>
    <?php
};
?>
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= e($title) ?></h1>
        <p class="mt-1 text-sm text-slate-500">
            Edit safe runtime settings without touching files over SSH. Changes are written to
            <code class="text-slate-600"><?= e(basename($envPath)) ?></code> and take effect on the next request.
        </p>
    </div>

    <?php if (! $envExists): ?>
        <div class="alert-error">
            <svg class="h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <span>No <code><?= e(basename($envPath)) ?></code> file was found. Run the installer first to generate one.</span>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= e(url('system/environment')) ?>" class="space-y-6">
        <?= csrf_field() ?>

        <?php foreach ($groups as $groupKey => $keys): ?>
            <div class="card">
                <div class="card-body space-y-4">
                    <h2 class="text-lg font-semibold text-slate-900"><?= e($groupTitles[$groupKey] ?? ucfirst($groupKey)) ?></h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <?php foreach ($keys as $key): ?>
                            <?php $renderField($key); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary">Save changes</button>
            <a href="<?= e(url('system/environment')) ?>" class="btn-secondary">Reset</a>
        </div>
    </form>

    <!-- Read-only secrets -->
    <div class="card">
        <div class="card-body space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Secrets</h2>
                <p class="mt-1 text-sm text-slate-500">
                    These are shown for confirmation only and cannot be edited here. Database credentials
                    are managed via the installer for safety, so a stray change can't lock the app out of its database.
                </p>
            </div>
            <dl class="divide-y divide-slate-100 rounded-lg border border-slate-200">
                <?php foreach ($secrets as $key => $info): ?>
                    <div class="flex items-center justify-between px-4 py-3">
                        <dt class="font-mono text-sm text-slate-700"><?= e($key) ?></dt>
                        <dd>
                            <?php if (! empty($info['present'])): ?>
                                <span class="badge-slate"><?= e($info['masked']) ?></span>
                            <?php else: ?>
                                <span class="text-sm text-slate-400"><?= e($info['masked']) ?></span>
                            <?php endif; ?>
                        </dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
