<?php
/** @var list<array<string,mixed>> $templates */
/** @var string|null $status */
?>
<div class="mb-6 flex items-start justify-between">
    <div>
        <a href="/workflows" data-pjax class="text-xs font-medium text-slate-400 hover:text-slate-600">&larr; Workflows</a>
        <h1 class="mt-1 text-2xl font-semibold text-slate-900">Templates</h1>
        <p class="mt-1 text-sm text-slate-500">Start from a ready-made automation. Pick one and tweak it in the builder &mdash; it’s created disabled so you can review before enabling.</p>
    </div>
    <a href="/workflows/new" data-pjax class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Start from scratch</a>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($templates as $t): ?>
        <div class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-2 flex items-center gap-2">
                <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-indigo-600"><?= e($t['category']) ?></span>
                <span class="text-[11px] text-slate-400"><?= count($t['nodes']) ?> steps</span>
            </div>
            <h2 class="text-sm font-semibold text-slate-900"><?= e($t['name']) ?></h2>
            <p class="mt-1 flex-1 text-sm text-slate-500"><?= e($t['description']) ?></p>
            <form method="post" action="/workflows/templates/<?= e($t['key']) ?>/use" class="mt-4">
                <?= csrf_field() ?>
                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Use this template</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
