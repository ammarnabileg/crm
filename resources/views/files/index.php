<?php
/** @var list<array<string,mixed>> $files */
/** @var string|null $status */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Files</h1>
    <p class="mt-1 text-sm text-slate-500">All files in this workspace. Downloads are permission-gated and never shared across workspaces.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <?php if ($files === []): ?>
        <p class="px-5 py-6 text-sm text-slate-400">No files yet. Upload CVs and attachments from a candidate profile.</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($files as $f): ?>
                <li class="flex items-center justify-between px-5 py-3 text-sm">
                    <div>
                        <a href="/files/<?= e($f['id']) ?>/download" class="font-medium text-indigo-600 hover:underline"><?= e($f['original_name']) ?></a>
                        <?php if (! empty($f['entity_type'])): ?><span class="text-xs text-slate-400">· <?= e($f['entity_type']) ?></span><?php endif; ?>
                        <div class="text-xs text-slate-400"><?= e($f['mime']) ?> · <?= e($f['created_at']) ?> UTC</div>
                    </div>
                    <span class="text-xs text-slate-500"><?= e(number_format(((int) $f['size_bytes']) / 1024, 1)) ?> KB</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
