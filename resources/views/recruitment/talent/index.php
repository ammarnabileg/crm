<?php
/** @var list<array<string,mixed>> $pools */
/** @var bool $canManage */
/** @var string|null $status */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Talent Pool</h1>
    <p class="mt-1 text-sm text-slate-500">Saved candidates you may want for future roles.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white shadow-sm">
        <?php if ($pools === []): ?>
            <p class="px-5 py-6 text-sm text-slate-400">No pools yet. Create one to start saving candidates.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($pools as $p): ?>
                    <li class="flex items-center justify-between px-5 py-3 text-sm">
                        <div>
                            <a href="/talent-pool/<?= e($p['id']) ?>" class="font-medium text-indigo-600 hover:underline"><?= e($p['name']) ?></a>
                            <?php if (! empty($p['description'])): ?><div class="text-xs text-slate-400"><?= e($p['description']) ?></div><?php endif; ?>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600"><?= e($p['members']) ?> saved</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm self-start">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">New pool</h2>
            <form method="post" action="/talent-pool" class="space-y-2">
                <?= csrf_field() ?>
                <input name="name" required placeholder="e.g. Senior PHP — future" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="description" placeholder="Optional description" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create pool</button>
            </form>
        </div>
    <?php endif; ?>
</div>
