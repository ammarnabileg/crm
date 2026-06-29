<?php
/** @var array<string,array{0:string,1:string}> $fields */
/** @var array<string,string> $values */
/** @var bool $entitled */
/** @var bool $hasLogo */
/** @var bool $canManage */
/** @var string|null $status */
/** @var string|null $error */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Branding Center</h1>
    <p class="mt-1 text-sm text-slate-500">Your company identity, applied across every candidate-facing surface.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

<?php if (! $entitled): ?>
    <div class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
        White Label & Branding Center is a premium service. <a href="/billing" class="font-semibold underline">Enable it in Build Your Workspace</a> to apply your company branding.
    </div>
<?php endif; ?>

<form method="post" action="/branding" class="max-w-3xl space-y-6">
    <?= csrf_field() ?>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="text-sm font-medium text-slate-700">Logo</div>
        <p class="mt-1 text-xs text-slate-400">Upload your logo from <a href="/settings" class="text-indigo-600 hover:underline">Settings</a>. <?= $hasLogo ? 'A logo is currently set.' : 'No logo uploaded yet.' ?> Dark logo, favicon and cover image follow the same upload pattern.</p>
    </div>

    <div class="grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:grid-cols-2">
        <?php foreach ($fields as $key => $meta): ?>
            <?php [$label, $type] = $meta; $val = $values[$key] ?? ''; ?>
            <div class="<?= $type === 'textarea' ? 'sm:col-span-2' : '' ?>">
                <label class="block text-sm font-medium text-slate-700"><?= e($label) ?></label>
                <?php if ($type === 'textarea'): ?>
                    <textarea name="<?= e($key) ?>" rows="2" <?= $entitled ? '' : 'disabled' ?> class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><?= e($val) ?></textarea>
                <?php elseif ($type === 'color'): ?>
                    <input type="color" name="<?= e($key) ?>" value="<?= e($val !== '' ? $val : '#4f46e5') ?>" <?= $entitled ? '' : 'disabled' ?> class="mt-1 h-10 w-20 rounded border border-slate-300">
                <?php else: ?>
                    <input type="<?= e($type) ?>" name="<?= e($key) ?>" value="<?= e($val) ?>" <?= $entitled ? '' : 'disabled' ?> class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($canManage && $entitled): ?>
        <button class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Save branding</button>
    <?php endif; ?>
</form>
