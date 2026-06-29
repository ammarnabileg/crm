<?php
/** @var list<array<string,mixed>> $roles */
/** @var array<string,string> $catalog */
/** @var list<array<string,mixed>> $users */
/** @var string|null $status */
/** @var string|null $error */
$field = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
$permsBox = static function (array $catalog, array $checked): string {
    $html = '<div class="grid gap-2 sm:grid-cols-2">';
    foreach ($catalog as $key => $label) {
        $on = in_array($key, $checked, true) ? 'checked' : '';
        $html .= '<label class="flex items-start gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">'
            . '<input type="checkbox" name="permissions[]" value="' . e($key) . '" ' . $on . ' class="mt-0.5 rounded border-slate-300">'
            . '<span><span class="block font-medium text-slate-700">' . e($label) . '</span>'
            . '<span class="block font-mono text-xs text-slate-400">' . e($key) . '</span></span></label>';
    }

    return $html . '</div>';
};
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Roles &amp; Permissions</h1>
    <p class="mt-1 text-sm text-slate-500">Define platform roles from the permission catalog and assign them to users — granular site managers without making everyone a full System Owner.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

<div class="space-y-5">
    <?php foreach ($roles as $role): ?>
        <?php $rid = (string) $role['id']; $holders = $role['holders']; $heldKeys = $role['permissions']; ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="post" action="/admin/roles/<?= e($rid) ?>/edit" class="space-y-4">
                <?= csrf_field() ?>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label class="mb-1 block text-xs font-medium text-slate-600">Role name</label><input name="name" value="<?= e($role['name']) ?>" class="<?= $field ?>"></div>
                    <div><label class="mb-1 block text-xs font-medium text-slate-600">Description</label><input name="description" value="<?= e($role['description'] ?? '') ?>" class="<?= $field ?>"></div>
                </div>
                <div>
                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Permissions</div>
                    <?= $permsBox($catalog, $heldKeys) ?>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400"><?= count($heldKeys) ?> permission(s) · <?= count($holders) ?> holder(s)</span>
                    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save role</button>
                </div>
            </form>

            <div class="mt-4 border-t border-slate-100 pt-4">
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Site managers with this role</div>
                <div class="flex flex-wrap items-center gap-2">
                    <?php foreach ($holders as $h): ?>
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700">
                            <?= e($h['name']) ?>
                            <form method="post" action="/admin/roles/<?= e($rid) ?>/unassign" class="inline">
                                <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= e($h['id']) ?>">
                                <button class="ms-0.5 text-slate-400 hover:text-rose-600" title="Remove">&times;</button>
                            </form>
                        </span>
                    <?php endforeach; ?>
                    <?php if ($holders === []): ?><span class="text-xs text-slate-400">No one assigned yet.</span><?php endif; ?>
                </div>
                <div class="mt-3 flex items-end gap-2">
                    <form method="post" action="/admin/roles/<?= e($rid) ?>/assign" class="flex items-end gap-2">
                        <?= csrf_field() ?>
                        <select name="user_id" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            <option value="">Assign a user…</option>
                            <?php foreach ($users as $u): ?><option value="<?= e($u['id']) ?>"><?= e($u['name']) ?> — <?= e($u['email']) ?></option><?php endforeach; ?>
                        </select>
                        <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Assign</button>
                    </form>
                    <form method="post" action="/admin/roles/<?= e($rid) ?>/delete" onsubmit="return confirm('Delete role “<?= e($role['name']) ?>”? Holders lose these permissions.')" class="ms-auto">
                        <?= csrf_field() ?><button class="rounded-lg px-3 py-1.5 text-sm font-medium text-rose-600 hover:bg-rose-50">Delete role</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if ($roles === []): ?>
        <div class="rounded-2xl border border-slate-200 bg-white px-5 py-8 text-center text-sm text-slate-400 shadow-sm">No platform roles yet. Create one below to delegate platform access.</div>
    <?php endif; ?>
</div>

<div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="mb-3 text-sm font-semibold text-slate-900">New role</h2>
    <form method="post" action="/admin/roles" class="space-y-4">
        <?= csrf_field() ?>
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="mb-1 block text-xs font-medium text-slate-600">Role name</label><input name="name" required class="<?= $field ?>" placeholder="e.g. Support Admin"></div>
            <div><label class="mb-1 block text-xs font-medium text-slate-600">Description</label><input name="description" class="<?= $field ?>" placeholder="What this role can do"></div>
        </div>
        <div>
            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Permissions</div>
            <?= $permsBox($catalog, []) ?>
        </div>
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Create role</button>
    </form>
</div>
