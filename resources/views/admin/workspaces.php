<?php /** @var list<array<string,mixed>> $workspaces */ /** @var string|null $status */ ?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Workspaces</h1>
    <p class="mt-1 text-sm text-slate-500">Every company/workspace on the platform.</p>
</div>
<?php if (! empty($status)): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
            <tr><th class="px-5 py-3">Name</th><th class="px-5 py-3">Owner</th><th class="px-5 py-3">Members</th><th class="px-5 py-3">Plan</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($workspaces as $w): ?>
                <?php
                $st = (string) $w['status'];
                $stc = $st === 'active' ? 'bg-emerald-100 text-emerald-700' : ($st === 'suspended' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600');
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-medium text-slate-800"><?= e($w['name']) ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($w['owner_name'] ?? '—') ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($w['members']) ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($w['plan_name'] ?? '—') ?> <span class="text-xs text-slate-400"><?= e($w['sub_status'] ?? '') ?></span></td>
                    <td class="px-5 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $stc ?>"><?= e($st) ?></span></td>
                    <td class="px-5 py-3">
                        <div class="flex items-center justify-end gap-1.5">
                            <?php if ($st === 'suspended'): ?>
                                <form method="post" action="/all-workspaces/<?= e($w['id']) ?>/resume"><?= csrf_field() ?><button class="rounded border border-emerald-300 px-2.5 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Resume</button></form>
                            <?php else: ?>
                                <form method="post" action="/all-workspaces/<?= e($w['id']) ?>/suspend" onsubmit="return confirm('Suspend this workspace?');"><?= csrf_field() ?><button class="rounded border border-rose-300 px-2.5 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50">Suspend</button></form>
                            <?php endif; ?>
                            <?php if ($st === 'archived'): ?>
                                <form method="post" action="/all-workspaces/<?= e($w['id']) ?>/restore"><?= csrf_field() ?><button class="rounded border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Restore</button></form>
                            <?php else: ?>
                                <form method="post" action="/all-workspaces/<?= e($w['id']) ?>/archive" onsubmit="return confirm('Archive this workspace?');"><?= csrf_field() ?><button class="rounded border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">Archive</button></form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($workspaces === []): ?><tr><td colspan="6" class="px-5 py-8 text-center text-sm text-slate-400">No workspaces yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
