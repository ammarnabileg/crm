<?php
/** @var array<string,mixed> $workspace */
/** @var array<string,string> $prefs */
/** @var bool $canUpdate */
/** @var string|null $status */
$p = static fn (string $k): string => (string) ($prefs[$k] ?? '');
$on = static fn (string $k): bool => ($prefs[$k] ?? '') === '1';
$dis = $canUpdate ? '' : 'disabled';
$inp = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none disabled:bg-slate-50';
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Workspace settings</h1>
    <p class="mt-1 text-sm text-slate-500">Each workspace has its own independent settings.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($on('maintenance.enabled')): ?>
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">⚠ Maintenance mode is ON — only members with <span class="font-mono">settings.update</span> can use the workspace.</div>
<?php endif; ?>

<form method="post" action="/settings" class="max-w-2xl space-y-6">
    <?= csrf_field() ?>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-slate-900">General</h2>
        <div class="space-y-4">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Workspace name</label>
                <input name="name" value="<?= e($workspace['name']) ?>" <?= $dis ?> class="<?= $inp ?>">
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Timezone</label><input name="timezone" value="<?= e($workspace['timezone']) ?>" <?= $dis ?> class="<?= $inp ?>"></div>
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Locale</label><input name="locale" value="<?= e($workspace['locale']) ?>" <?= $dis ?> class="<?= $inp ?>"></div>
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Currency</label><input name="currency" value="<?= e($workspace['currency']) ?>" <?= $dis ?> class="<?= $inp ?>"></div>
            </div>
            <div class="text-xs text-slate-400">Slug: <span class="font-mono"><?= e($workspace['slug']) ?></span></div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-slate-900">Company profile</h2>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Industry</label><input name="company_industry" value="<?= e($p('company.industry')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="e.g. Software"></div>
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Website</label><input name="company_website" value="<?= e($p('company.website')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="https://"></div>
            </div>
            <div><label class="mb-1 block text-sm font-medium text-slate-700">About</label><textarea name="company_about" rows="3" <?= $dis ?> class="<?= $inp ?>" placeholder="What your company does…"><?= e($p('company.about')) ?></textarea></div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-slate-900">Branding</h2>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="mb-1 block text-sm font-medium text-slate-700">Brand colour</label><input name="brand_color" type="text" value="<?= e($p('brand.color')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="#4f46e5"></div>
            <div><label class="mb-1 block text-sm font-medium text-slate-700">Tagline</label><input name="brand_tagline" value="<?= e($p('brand.tagline')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="Hiring, reimagined"></div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-slate-900">Security</h2>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="security_enforce_2fa" value="1" <?= $on('security.enforce_2fa') ? 'checked' : '' ?> <?= $dis ?> class="rounded border-slate-300"> Require two-factor authentication for members
        </label>
        <div class="mt-3"><label class="mb-1 block text-sm font-medium text-slate-700">Session timeout (minutes)</label><input name="security_session_timeout" type="number" min="5" value="<?= e($p('security.session_timeout')) ?>" <?= $dis ?> class="<?= $inp ?> max-w-[12rem]" placeholder="120"></div>
    </div>

    <div class="rounded-2xl border border-amber-200 bg-white p-6 shadow-sm">
        <h2 class="mb-1 text-sm font-semibold text-slate-900">Maintenance</h2>
        <p class="mb-4 text-xs text-slate-400">When on, the workspace pauses for everyone except admins (members with <span class="font-mono">settings.update</span>).</p>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="maintenance_enabled" value="1" <?= $on('maintenance.enabled') ? 'checked' : '' ?> <?= $dis ?> class="rounded border-slate-300"> Enable maintenance mode
        </label>
        <div class="mt-3"><label class="mb-1 block text-sm font-medium text-slate-700">Message shown to members</label><input name="maintenance_message" value="<?= e($p('maintenance.message')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="We'll be back shortly."></div>
    </div>

    <?php if ($canUpdate): ?>
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save settings</button>
    <?php endif; ?>
</form>

<?php if (! empty($isOwner)): ?>
    <div class="mt-6 max-w-2xl rounded-2xl border border-rose-200 bg-white p-6 shadow-sm">
        <h2 class="mb-1 text-sm font-semibold text-rose-700">Danger zone</h2>
        <p class="mb-4 text-xs text-slate-400">Owner-only actions for this workspace.</p>

        <form method="post" action="/workspaces/transfer-ownership" class="mb-5 flex flex-wrap items-end gap-2" onsubmit="return confirm('Transfer ownership to this user? You will keep your role but no longer be the owner.');">
            <?= csrf_field() ?>
            <label class="block text-xs font-medium text-slate-600">Transfer ownership to (email)
                <input name="email" type="email" required placeholder="new-owner@example.com" class="mt-1 w-72 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Transfer</button>
        </form>

        <form method="post" action="/workspaces/archive" onsubmit="return confirm('Archive this workspace? It will be paused until restored.');">
            <?= csrf_field() ?>
            <button class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">Archive workspace</button>
        </form>
    </div>
<?php endif; ?>
