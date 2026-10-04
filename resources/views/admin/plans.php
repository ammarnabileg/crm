<?php
/** @var list<array<string,mixed>> $plans */
/** @var string|null $status */
/** @var string|null $error */
$allFeatures = ['ai' => 'AI', 'automation' => 'Automation', 'integrations' => 'Integrations'];
$intervals = ['month' => 'Monthly', 'year' => 'Yearly', 'none' => 'One-off / free'];
$field = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm';
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Plans</h1>
    <p class="mt-1 text-sm text-slate-500">Subscription plans and the number of workspaces each one allows.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

<div class="space-y-4">
    <?php foreach ($plans as $p): ?>
        <?php $limits = is_array($p['limits'] ?? null) ? $p['limits'] : []; $feats = is_array($p['features'] ?? null) ? $p['features'] : []; ?>
        <form method="post" action="/plans/<?= e($p['id']) ?>/edit" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <?= csrf_field() ?>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900"><?= e($p['name']) ?> <span class="font-mono text-xs text-slate-400"><?= e($p['code']) ?></span></h2>
                <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700"><?= (int) ($limits['workspaces'] ?? 1) ?> workspace(s)</span>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <div><label class="mb-1 block text-xs font-medium text-slate-600">Name</label><input name="name" value="<?= e($p['name']) ?>" class="<?= $field ?>"></div>
                <div><label class="mb-1 block text-xs font-medium text-slate-600">Price</label><input name="price" type="number" step="0.01" min="0" value="<?= e(number_format(((int) $p['price_cents']) / 100, 2, '.', '')) ?>" class="<?= $field ?>"></div>
                <div><label class="mb-1 block text-xs font-medium text-slate-600">Currency</label><input name="currency" value="<?= e($p['currency']) ?>" class="<?= $field ?>"></div>
                <div><label class="mb-1 block text-xs font-medium text-slate-600">Max workspaces</label><input name="max_workspaces" type="number" min="1" value="<?= (int) ($limits['workspaces'] ?? 1) ?>" class="<?= $field ?>"></div>
                <div><label class="mb-1 block text-xs font-medium text-slate-600">Interval</label>
                    <select name="interval" class="<?= $field ?>"><?php foreach ($intervals as $v => $l): ?><option value="<?= $v ?>" <?= ($p['interval'] ?? 'month') === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
                </div>
                <div><label class="mb-1 block text-xs font-medium text-slate-600">Trial days</label><input name="trial_days" type="number" min="0" value="<?= (int) $p['trial_days'] ?>" class="<?= $field ?>"></div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-4">
                <?php foreach ($allFeatures as $fk => $fl): ?>
                    <label class="flex items-center gap-1.5 text-sm text-slate-600"><input type="checkbox" name="features[]" value="<?= $fk ?>" <?= in_array($fk, $feats, true) ? 'checked' : '' ?> class="rounded border-slate-300"> <?= $fl ?></label>
                <?php endforeach; ?>
                <label class="flex items-center gap-1.5 text-sm text-slate-600"><input type="checkbox" name="is_public" value="1" <?= (int) ($p['is_public'] ?? 0) === 1 ? 'checked' : '' ?> class="rounded border-slate-300"> Public</label>
                <input name="sort" type="number" value="<?= (int) ($p['sort'] ?? 0) ?>" class="w-20 rounded-lg border border-slate-300 px-2 py-1 text-sm" title="Sort order">
                <div class="ml-auto flex gap-2">
                    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
                </div>
            </div>
        </form>
        <form method="post" action="/plans/<?= e($p['id']) ?>/delete" onsubmit="return confirm('Delete plan “<?= e($p['name']) ?>”? (Only allowed if no account uses it.)')" class="-mt-2 pl-1">
            <?= csrf_field() ?>
            <button class="text-xs font-medium text-rose-600 hover:text-rose-700">Delete plan</button>
        </form>
    <?php endforeach; ?>
</div>

<div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="mb-3 text-sm font-semibold text-slate-900">New plan</h2>
    <form method="post" action="/plans" class="grid gap-3 sm:grid-cols-3">
        <?= csrf_field() ?>
        <div><label class="mb-1 block text-xs font-medium text-slate-600">Name</label><input name="name" required class="<?= $field ?>" placeholder="Growth"></div>
        <div><label class="mb-1 block text-xs font-medium text-slate-600">Code</label><input name="code" class="<?= $field ?>" placeholder="growth (optional)"></div>
        <div><label class="mb-1 block text-xs font-medium text-slate-600">Max workspaces</label><input name="max_workspaces" type="number" min="1" value="1" class="<?= $field ?>"></div>
        <div><label class="mb-1 block text-xs font-medium text-slate-600">Price</label><input name="price" type="number" step="0.01" min="0" value="0" class="<?= $field ?>"></div>
        <div><label class="mb-1 block text-xs font-medium text-slate-600">Currency</label><input name="currency" value="USD" class="<?= $field ?>"></div>
        <div><label class="mb-1 block text-xs font-medium text-slate-600">Interval</label><select name="interval" class="<?= $field ?>"><?php foreach ($intervals as $v => $l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="sm:col-span-3 flex flex-wrap items-center gap-4">
            <?php foreach ($allFeatures as $fk => $fl): ?>
                <label class="flex items-center gap-1.5 text-sm text-slate-600"><input type="checkbox" name="features[]" value="<?= $fk ?>" class="rounded border-slate-300"> <?= $fl ?></label>
            <?php endforeach; ?>
            <label class="flex items-center gap-1.5 text-sm text-slate-600"><input type="checkbox" name="is_public" value="1" checked class="rounded border-slate-300"> Public</label>
            <button class="ml-auto rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Create plan</button>
        </div>
    </form>
</div>
