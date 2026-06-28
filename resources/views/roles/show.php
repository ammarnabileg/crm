<?php
/** @var array<string,mixed> $role */
/** @var list<string> $permissionKeys */
/** @var array<string, list<array{key:string,description:string}>> $catalog */
/** @var list<array<string,mixed>> $holders */
/** @var bool $canUpdate */
/** @var string|null $status */
/** @var string|null $error */
$held = array_fill_keys($permissionKeys, true);
?>
<div class="mb-6">
    <a href="/roles" class="text-xs text-slate-400 hover:text-slate-600">← Roles</a>
    <h1 class="mt-1 text-2xl font-semibold text-slate-900"><?= e($role['name']) ?></h1>
    <p class="mt-1 text-sm text-slate-500"><?= count($permissionKeys) ?> permission(s) · <?= count($holders) ?> member(s) hold this role</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <!-- Members holding this role -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Members with this role</h2>
        <?php if ($holders === []): ?>
            <p class="text-sm text-slate-400">No members hold this role yet.</p>
        <?php else: ?>
            <ul class="space-y-2 text-sm">
                <?php foreach ($holders as $h): ?>
                    <li class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2">
                        <div class="min-w-0">
                            <div class="font-medium text-slate-800"><?= e($h['name']) ?></div>
                            <div class="text-xs text-slate-400"><?= e($h['email']) ?></div>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium <?= (string) $h['status'] === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-500' ?>"><?= e($h['status']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Permissions (editable) -->
    <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-slate-900"><?= $canUpdate ? 'Edit role' : 'Permissions' ?></h2>
        <?php if ($canUpdate): ?>
            <form method="post" action="/roles/<?= e($role['id']) ?>/edit">
                <?= csrf_field() ?>
                <div class="mb-4 max-w-sm">
                    <label class="mb-1 block text-xs font-medium text-slate-600">Role name</label>
                    <input name="name" required value="<?= e($role['name']) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                </div>
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($catalog as $category => $perms): ?>
                        <div>
                            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400"><?= e($category) ?></div>
                            <div class="space-y-1">
                                <?php foreach ($perms as $p): ?>
                                    <label class="flex items-start gap-2 text-sm text-slate-600">
                                        <input type="checkbox" name="permissions[]" value="<?= e($p['key']) ?>" <?= isset($held[$p['key']]) ? 'checked' : '' ?> class="mt-1 rounded border-slate-300 text-indigo-600">
                                        <span><span class="font-mono text-xs text-slate-500"><?= e($p['key']) ?></span></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="mt-6 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save changes</button>
            </form>
        <?php else: ?>
            <?php if ($permissionKeys === []): ?>
                <p class="text-sm text-slate-400">This role grants no permissions.</p>
            <?php else: ?>
                <div class="flex flex-wrap gap-1.5">
                    <?php foreach ($permissionKeys as $key): ?>
                        <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600"><?= e($key) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
