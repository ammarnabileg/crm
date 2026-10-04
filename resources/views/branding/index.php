<?php
/** @var array<string,array{label:string,type:string,group:string,options?:array<string,string>,help?:string}> $fields */
/** @var array<string,string> $values */
/** @var bool $entitled */
/** @var bool $hasLogo */
/** @var bool $canManage */
/** @var string|null $status */
/** @var string|null $error */

// Group the flat field schema for sectioned rendering (order preserved).
$groups = [];
foreach ($fields as $key => $meta) {
    $groups[$meta['group']][$key] = $meta;
}
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Branding Center</h1>
    <p class="mt-1 text-sm text-slate-500">Your company identity — colours, typography, logo and copy — applied across every workspace surface from one place.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

<?php if (! $entitled): ?>
    <div class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
        White Label &amp; Branding Center is a premium service. <a href="/billing" class="font-semibold underline">Enable it in Build Your Workspace</a> to apply your company branding everywhere.
    </div>
<?php endif; ?>

<form method="post" action="/branding" class="max-w-3xl space-y-6">
    <?= csrf_field() ?>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="text-sm font-semibold text-slate-700">Logo</div>
        <p class="mt-1 text-xs text-slate-400">Your primary logo is uploaded from <a href="/settings" class="text-indigo-600 hover:underline">Settings</a>. <?= $hasLogo ? 'A logo is currently set and shown across the app.' : 'No logo uploaded yet — your initial is shown instead.' ?></p>
    </div>

    <?php foreach ($groups as $groupName => $groupFields): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4 text-sm font-semibold text-slate-700"><?= e($groupName) ?></div>
            <div class="grid gap-5 sm:grid-cols-2">
                <?php foreach ($groupFields as $key => $meta): ?>
                    <?php $type = $meta['type']; $val = $values[$key] ?? ''; $full = in_array($type, ['textarea'], true); ?>
                    <div class="<?= $full ? 'sm:col-span-2' : '' ?>">
                        <label class="block text-sm font-medium text-slate-700" for="brand-<?= e($key) ?>"><?= e($meta['label']) ?></label>
                        <?php if ($type === 'textarea'): ?>
                            <textarea id="brand-<?= e($key) ?>" name="<?= e($key) ?>" rows="2" <?= $entitled ? '' : 'disabled' ?> class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"><?= e($val) ?></textarea>
                        <?php elseif ($type === 'select'): ?>
                            <select id="brand-<?= e($key) ?>" name="<?= e($key) ?>" <?= $entitled ? '' : 'disabled' ?> class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                <?php foreach (($meta['options'] ?? []) as $optVal => $optLabel): ?>
                                    <option value="<?= e($optVal) ?>" <?= $val === $optVal ? 'selected' : '' ?>><?= e($optLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($type === 'color'): ?>
                            <input id="brand-<?= e($key) ?>" type="color" name="<?= e($key) ?>" value="<?= e($val !== '' ? $val : '#4f46e5') ?>" <?= $entitled ? '' : 'disabled' ?> class="mt-1 h-10 w-20 rounded border border-slate-300">
                        <?php else: ?>
                            <input id="brand-<?= e($key) ?>" type="<?= e($type) ?>" name="<?= e($key) ?>" value="<?= e($val) ?>" <?= $entitled ? '' : 'disabled' ?> class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        <?php endif; ?>
                        <?php if (! empty($meta['help'])): ?>
                            <p class="mt-1 text-xs text-slate-400"><?= e($meta['help']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($canManage && $entitled): ?>
        <button class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Save branding</button>
    <?php endif; ?>
</form>
