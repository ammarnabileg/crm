<?php
/** @var array<string,mixed> $workflow */
/** @var list<array<string,mixed>> $versions */
/** @var bool $canRestore */
/** @var string|null $status */
?>
<div class="mb-6">
    <a href="/workflows/<?= e($workflow['id']) ?>/edit" data-pjax class="text-xs font-medium text-slate-400 hover:text-slate-600">&larr; Back to builder</a>
    <h1 class="mt-1 text-2xl font-semibold text-slate-900">Version history</h1>
    <p class="mt-1 text-sm text-slate-500">Every save is snapshotted. Roll back to any version &mdash; the rollback is itself saved as a new version, so nothing is lost.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <?php if ($versions === []): ?>
        <p class="px-5 py-6 text-sm text-slate-400">No versions yet.</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($versions as $i => $v): ?>
                <li class="flex items-start justify-between gap-3 px-5 py-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">v<?= e($v['version']) ?></span>
                            <?php if ($i === 0): ?><span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">Current</span><?php endif; ?>
                        </div>
                        <div class="mt-1 text-sm text-slate-700"><?= e($v['summary'] ?? '') ?: '<span class="text-slate-400">No summary</span>' ?></div>
                        <div class="mt-0.5 text-xs text-slate-400"><?= e($v['created_at']) ?> UTC<?php if (! empty($v['author'])): ?> · <?= e($v['author']) ?><?php endif; ?></div>
                    </div>
                    <?php if ($canRestore && $i !== 0): ?>
                        <form method="post" action="/workflows/<?= e($workflow['id']) ?>/versions/<?= e($v['id']) ?>/restore" class="shrink-0">
                            <?= csrf_field() ?>
                            <button class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50">Restore</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
