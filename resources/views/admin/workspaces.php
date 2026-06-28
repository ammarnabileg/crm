<?php /** @var list<array<string,mixed>> $workspaces */ ?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Workspaces</h1>
    <p class="mt-1 text-sm text-slate-500">Every company/workspace on the platform.</p>
</div>
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
            <tr><th class="px-5 py-3">Name</th><th class="px-5 py-3">Owner</th><th class="px-5 py-3">Members</th><th class="px-5 py-3">Plan</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Created</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($workspaces as $w): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-medium text-slate-800"><?= e($w['name']) ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($w['owner_name'] ?? '—') ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($w['members']) ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($w['plan_name'] ?? '—') ?> <span class="text-xs text-slate-400"><?= e($w['sub_status'] ?? '') ?></span></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($w['status']) ?></td>
                    <td class="px-5 py-3 text-xs text-slate-400"><?= e($w['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($workspaces === []): ?><tr><td colspan="6" class="px-5 py-8 text-center text-sm text-slate-400">No workspaces yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
