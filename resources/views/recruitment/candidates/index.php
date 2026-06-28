<?php /** @var list<array<string,mixed>> $candidates */ ?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Candidates</h1>
    <p class="mt-1 text-sm text-slate-500">People who interacted with this workspace. You only ever see your workspace's view.</p>
</div>

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <?php if ($candidates === []): ?>
        <p class="px-5 py-8 text-center text-sm text-slate-400">No candidates yet. They appear here when someone applies.</p>
    <?php else: ?>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                <tr><th class="px-5 py-3">Name</th><th class="px-5 py-3">Email</th><th class="px-5 py-3">Applications</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($candidates as $c): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3"><a href="/candidates/<?= e($c['user_id']) ?>" class="font-medium text-indigo-600 hover:underline"><?= e($c['name']) ?></a></td>
                        <td class="px-5 py-3 text-slate-600"><?= e($c['email']) ?></td>
                        <td class="px-5 py-3 text-slate-600"><?= e($c['applications']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
