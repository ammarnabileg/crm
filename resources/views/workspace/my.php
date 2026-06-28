<?php
/** @var list<array<string,mixed>> $workspaces */
/** @var string|null $currentId */
/** @var array{total:int,active:int,suspended:int} $stats */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">My Workspaces</h1>
    <p class="mt-1 text-sm text-slate-500">Every workspace you belong to — in any role.</p>
</div>

<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Total</div><div class="mt-1 text-2xl font-bold text-slate-900"><?= e($stats['total']) ?></div></div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Active</div><div class="mt-1 text-2xl font-bold text-emerald-700"><?= e($stats['active']) ?></div></div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Suspended</div><div class="mt-1 text-2xl font-bold text-rose-700"><?= e($stats['suspended']) ?></div></div>
</div>

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
            <tr><th class="px-5 py-3">Workspace</th><th class="px-5 py-3">Owner</th><th class="px-5 py-3">Members</th><th class="px-5 py-3">Plan</th><th class="px-5 py-3">Created</th><th class="px-5 py-3"></th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($workspaces as $w): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-medium text-slate-800">
                        <?= e($w['name']) ?>
                        <?php if ((string) $w['id'] === (string) $currentId): ?><span class="ms-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">current</span><?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-slate-600"><?= e($w['owner_name'] ?? '—') ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($w['members']) ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($w['plan_name'] ?? 'Free') ?></td>
                    <td class="px-5 py-3 text-xs text-slate-400"><?= e($w['created_at']) ?></td>
                    <td class="px-5 py-3 text-right">
                        <?php if ((string) $w['id'] !== (string) $currentId): ?>
                            <form method="post" action="/workspaces/<?= e($w['id']) ?>/switch">
                                <?= csrf_field() ?>
                                <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">Enter</button>
                            </form>
                        <?php else: ?>
                            <a href="/dashboard" class="text-xs font-medium text-indigo-600 hover:underline">Open</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
