<?php
/**
 * @var list<array<string,mixed>> $paths
 * @var bool $canManage
 * @var string|null $status
 */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Learning Paths</h1>
    <p class="mt-1 text-sm text-slate-500">Ordered tracks that group programs into a journey.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-3 lg:col-span-2">
        <?php if ($paths === []): ?>
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center text-sm text-slate-400 shadow-sm">No paths yet.</div>
        <?php else: ?>
            <?php foreach ($paths as $p): ?>
                <a href="/learning-paths/<?= e($p['id']) ?>" class="block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-indigo-300">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-slate-900"><?= e($p['title']) ?></h3>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600"><?= e(ucfirst((string) $p['status'])) ?></span>
                    </div>
                    <?php if (! empty($p['description'])): ?><p class="mt-1 text-sm text-slate-500"><?= e($p['description']) ?></p><?php endif; ?>
                    <p class="mt-2 text-xs text-slate-400"><?= (int) ($p['programs_count'] ?? 0) ?> program(s)</p>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
        <div class="self-start rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">New path</h2>
            <form method="post" action="/learning-paths" class="space-y-2">
                <?= csrf_field() ?>
                <input name="title" required placeholder="e.g. New Engineer Track" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <textarea name="description" rows="2" placeholder="Description" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create path</button>
            </form>
        </div>
    <?php endif; ?>
</div>
