<?php /** @var list<array<string,mixed>> $users */ ?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Users</h1>
    <p class="mt-1 text-sm text-slate-500">Every user across all workspaces — one identity, many contexts.</p>
</div>
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
            <tr><th class="px-5 py-3">Name</th><th class="px-5 py-3">Email</th><th class="px-5 py-3">Workspaces</th><th class="px-5 py-3">Type</th><th class="px-5 py-3">Created</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($users as $u): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-medium text-slate-800"><?= e($u['name']) ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($u['email']) ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($u['workspaces']) ?></td>
                    <td class="px-5 py-3">
                        <?php if ((int) $u['is_system_owner'] === 1): ?>
                            <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700">System Owner</span>
                        <?php else: ?>
                            <span class="text-xs text-slate-400">User</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-xs text-slate-400"><?= e($u['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($users === []): ?><tr><td colspan="5" class="px-5 py-8 text-center text-sm text-slate-400">No users.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
