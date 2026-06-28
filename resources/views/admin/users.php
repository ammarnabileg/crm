<?php
/** @var list<array<string,mixed>> $users */
/** @var string $q */
/** @var string $currentUserId */
/** @var string|null $status */
$statusStyles = [
    'active' => 'bg-emerald-50 text-emerald-700',
    'suspended' => 'bg-amber-50 text-amber-700',
    'deactivated' => 'bg-rose-50 text-rose-700',
];
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Users</h1>
        <p class="mt-1 text-sm text-slate-500">Every user across all workspaces — one identity, many contexts.</p>
    </div>
    <form method="get" action="/admin/users" class="flex items-center gap-2">
        <input name="q" value="<?= e($q) ?>" placeholder="Search name or email…" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        <button class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Search</button>
        <?php if ($q !== ''): ?><a href="/admin/users" class="text-sm text-slate-500 hover:text-slate-700">Clear</a><?php endif; ?>
    </form>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<p class="mb-3 text-xs text-slate-400"><?= count($users) ?> user(s)<?= $q !== '' ? ' matching "' . e($q) . '"' : '' ?>.</p>

<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
            <tr>
                <th class="px-5 py-3">Name</th>
                <th class="px-5 py-3">Workspaces</th>
                <th class="px-5 py-3">Type</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3">Last login</th>
                <th class="px-5 py-3">Created</th>
                <th class="px-5 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($users as $u): ?>
                <?php
                $st = (string) ($u['status'] ?? 'active');
                $isOwner = (int) $u['is_system_owner'] === 1;
                $isSelf = (string) $u['id'] === $currentUserId;
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3">
                        <div class="font-medium text-slate-800"><?= e($u['name']) ?><?php if ($isSelf): ?> <span class="text-xs text-slate-400">(you)</span><?php endif; ?></div>
                        <div class="text-xs text-slate-400"><?= e($u['email']) ?></div>
                    </td>
                    <td class="px-5 py-3 text-slate-600"><?= e($u['workspaces']) ?></td>
                    <td class="px-5 py-3">
                        <?php if ($isOwner): ?>
                            <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700">System Owner</span>
                        <?php else: ?>
                            <span class="text-xs text-slate-400">User</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $statusStyles[$st] ?? 'bg-slate-100 text-slate-500' ?>"><?= e($st) ?></span></td>
                    <td class="px-5 py-3 text-xs text-slate-500"><?= e(time_ago($u['last_login_at'] ?? null)) ?></td>
                    <td class="px-5 py-3 text-xs text-slate-400"><?= e(time_ago($u['created_at'] ?? null)) ?></td>
                    <td class="px-5 py-3">
                        <?php if ($isOwner || $isSelf): ?>
                            <span class="block text-right text-xs text-slate-300">—</span>
                        <?php else: ?>
                            <div class="flex items-center justify-end">
                                <?php if ($st === 'active'): ?>
                                    <form method="post" action="/admin/users/<?= e($u['id']) ?>/deactivate" onsubmit="return confirm('Deactivate <?= e($u['name']) ?>? They will not be able to sign in.')"><?= csrf_field() ?><button class="text-xs font-medium text-rose-600 hover:text-rose-700">Deactivate</button></form>
                                <?php else: ?>
                                    <form method="post" action="/admin/users/<?= e($u['id']) ?>/activate"><?= csrf_field() ?><button class="text-xs font-medium text-emerald-600 hover:text-emerald-700">Activate</button></form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($users === []): ?><tr><td colspan="7" class="px-5 py-8 text-center text-sm text-slate-400">No users<?= $q !== '' ? ' match your search' : '' ?>.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
