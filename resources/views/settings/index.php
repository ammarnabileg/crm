<?php /** @var array<string,mixed> $workspace */ /** @var bool $canUpdate */ /** @var string|null $status */ ?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Workspace settings</h1>
    <p class="mt-1 text-sm text-slate-500">Each workspace has its own independent settings.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<form method="post" action="/settings" class="max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <?= csrf_field() ?>
    <div class="space-y-4">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Workspace name</label>
            <input name="name" value="<?= e($workspace['name']) ?>" <?= $canUpdate ? '' : 'disabled' ?> class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none disabled:bg-slate-50">
        </div>
        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Timezone</label>
                <input name="timezone" value="<?= e($workspace['timezone']) ?>" <?= $canUpdate ? '' : 'disabled' ?> class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none disabled:bg-slate-50">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Locale</label>
                <input name="locale" value="<?= e($workspace['locale']) ?>" <?= $canUpdate ? '' : 'disabled' ?> class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none disabled:bg-slate-50">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Currency</label>
                <input name="currency" value="<?= e($workspace['currency']) ?>" <?= $canUpdate ? '' : 'disabled' ?> class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none disabled:bg-slate-50">
            </div>
        </div>
        <div class="text-xs text-slate-400">Slug: <span class="font-mono"><?= e($workspace['slug']) ?></span></div>
    </div>
    <?php if ($canUpdate): ?>
        <button type="submit" class="mt-6 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save settings</button>
    <?php endif; ?>
</form>
