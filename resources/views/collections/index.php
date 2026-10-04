<?php
/** @var list<array<string,mixed>> $collections */
/** @var bool $canManage */
/** @var string|null $status */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Collections</h1>
    <p class="mt-1 text-sm text-slate-500">Your workspace’s simple database. Build a collection, store records (by hand or from a workflow), and export to CSV anytime.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-3">
        <?php if ($collections === []): ?>
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center text-sm text-slate-400">
                No collections yet. Create one to start storing data.
            </div>
        <?php else: ?>
            <?php foreach ($collections as $c): ?>
                <a href="/collections/<?= e($c['id']) ?>" data-pjax class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm hover:border-indigo-300">
                    <div class="min-w-0">
                        <div class="font-semibold text-slate-800"><?= e($c['name']) ?></div>
                        <div class="mt-0.5 truncate text-xs text-slate-400">
                            <?php $labels = array_map(static fn ($f) => (string) ($f['label'] ?? $f['key'] ?? ''), $c['fields'] ?: []); ?>
                            <?= $labels ? e(implode(' · ', $labels)) : 'No fields defined' ?>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600"><?= e($c['records']) ?> records</span>
                        <span class="text-slate-300">&rarr;</span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm self-start">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">New collection</h2>
            <form method="post" action="/collections" class="space-y-3">
                <?= csrf_field() ?>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Name</label>
                    <input name="name" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. Candidate scores">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Fields</label>
                    <textarea name="fields" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="One per line, e.g.&#10;Candidate&#10;Score&#10;Stage"></textarea>
                    <p class="mt-1 text-xs text-slate-400">One field per line. You can change these later by recreating the collection.</p>
                </div>
                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create collection</button>
            </form>
        </div>
    <?php endif; ?>
</div>
