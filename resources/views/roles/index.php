<?php
/** @var list<array<string,mixed>> $roles */
/** @var array<string, list<array{key:string,description:string}>> $catalog */
/** @var bool $canCreate */
/** @var bool $canClone */
/** @var bool $canDelete */
/** @var string|null $status */
/** @var string|null $error */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Roles</h1>
    <p class="mt-1 text-sm text-slate-500">Roles are just bundles of permissions — build any role you need.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

<div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="mb-3 text-sm font-semibold text-slate-900">Existing roles (<?= count($roles) ?>)</h2>
    <?php if ($roles === []): ?>
        <p class="text-sm text-slate-400">No roles yet. Create one below.</p>
    <?php else: ?>
        <ul class="space-y-1 text-sm">
            <?php foreach ($roles as $r): ?>
                <?php $members = (int) ($r['members'] ?? 0); ?>
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-slate-50 px-3 py-2">
                    <div class="min-w-0">
                        <span class="font-medium text-slate-800"><?= e($r['name']) ?></span>
                        <?php if (! empty($r['description'])): ?><span class="ml-2 text-slate-500"><?= e($r['description']) ?></span><?php endif; ?>
                        <div class="mt-0.5 text-xs text-slate-400">
                            <?= (int) ($r['permissions'] ?? 0) ?> permission(s) ·
                            <?php if ($members > 0): ?><span class="text-slate-500"><?= $members ?> member(s) using this role</span><?php else: ?>not in use<?php endif; ?>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <a href="/roles/<?= e($r['id']) ?>" class="text-xs font-medium text-slate-600 hover:text-slate-800">View<?= $members > 0 ? ' · ' . $members . ' member(s)' : '' ?></a>
                        <?php if ($canClone || $canDelete): ?>
                            <?php if ($canClone): ?>
                                <form method="post" action="/roles/<?= e($r['id']) ?>/clone"><?= csrf_field() ?><button class="text-xs font-medium text-indigo-600 hover:text-indigo-700">Clone</button></form>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                                <?php if ($members > 0): ?>
                                    <span class="text-xs text-slate-300" title="Reassign its members before deleting">Delete</span>
                                <?php else: ?>
                                    <form method="post" action="/roles/<?= e($r['id']) ?>/delete" onsubmit="return confirm('Delete the role “<?= e($r['name']) ?>”?')"><?= csrf_field() ?><button class="text-xs font-medium text-rose-600 hover:text-rose-700">Delete</button></form>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php if ($canCreate): ?>
    <form method="post" action="/roles" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <?= csrf_field() ?>
        <h2 class="mb-4 text-sm font-semibold text-slate-900">Create a role</h2>
        <div class="mb-4 max-w-sm">
            <label class="mb-1 block text-xs font-medium text-slate-600">Role name</label>
            <input name="name" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="e.g. Recruiter">
        </div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($catalog as $category => $perms): ?>
                <div>
                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400"><?= e($category) ?></div>
                    <div class="space-y-1">
                        <?php foreach ($perms as $p): ?>
                            <label class="flex items-start gap-2 text-sm text-slate-600">
                                <input type="checkbox" name="permissions[]" value="<?= e($p['key']) ?>" class="mt-1 rounded border-slate-300 text-indigo-600">
                                <span><span class="font-mono text-xs text-slate-500"><?= e($p['key']) ?></span></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="mt-6 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create role</button>
    </form>
<?php endif; ?>
