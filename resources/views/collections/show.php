<?php
/** @var array<string,mixed> $collection */
/** @var list<array<string,mixed>> $records */
/** @var bool $canManage */
/** @var string|null $status */
$fields = $collection['fields'] ?: [];
?>
<div class="mb-6 flex items-start justify-between">
    <div>
        <a href="/collections" data-pjax class="text-xs font-medium text-slate-400 hover:text-slate-600">&larr; Collections</a>
        <h1 class="mt-1 text-2xl font-semibold text-slate-900"><?= e($collection['name']) ?></h1>
        <p class="mt-1 text-sm text-slate-500"><?= count($records) ?> record(s)</p>
    </div>
    <a href="/collections/<?= e($collection['id']) ?>/export" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        Export CSV
    </a>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<?php if ($canManage && $fields !== []): ?>
    <form method="post" action="/collections/<?= e($collection['id']) ?>/records" class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <?= csrf_field() ?>
        <div class="flex flex-wrap items-end gap-3">
            <?php foreach ($fields as $f): ?>
                <div class="min-w-[8rem] flex-1">
                    <label class="mb-1 block text-xs font-medium text-slate-600"><?= e($f['label'] ?? $f['key']) ?></label>
                    <input name="f_<?= e($f['key']) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            <?php endforeach; ?>
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add record</button>
        </div>
    </form>
<?php endif; ?>

<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="min-w-full text-sm">
        <thead class="border-b border-slate-100 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
            <tr>
                <?php foreach ($fields as $f): ?><th class="px-4 py-2"><?= e($f['label'] ?? $f['key']) ?></th><?php endforeach; ?>
                <?php if ($fields === []): ?><th class="px-4 py-2">Data</th><?php endif; ?>
                <th class="px-4 py-2 text-right">Added</th>
                <?php if ($canManage): ?><th class="px-4 py-2"></th><?php endif; ?>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if ($records === []): ?>
                <tr><td colspan="20" class="px-4 py-8 text-center text-slate-400">No records yet<?= $canManage && $fields !== [] ? ' — add one above, or write to this collection from a workflow.' : '.' ?></td></tr>
            <?php else: ?>
                <?php foreach ($records as $rec): ?>
                    <tr class="hover:bg-slate-50">
                        <?php if ($fields === []): ?>
                            <td class="px-4 py-2 font-mono text-xs text-slate-600"><?= e(json_encode($rec['data'])) ?></td>
                        <?php else: ?>
                            <?php foreach ($fields as $f): ?>
                                <td class="px-4 py-2 text-slate-700"><?= e((string) ($rec['data'][$f['key']] ?? '')) ?></td>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <td class="px-4 py-2 text-right text-xs text-slate-400"><?= e($rec['created_at']) ?></td>
                        <?php if ($canManage): ?>
                            <td class="px-4 py-2 text-right">
                                <form method="post" action="/collections/<?= e($collection['id']) ?>/records/<?= e($rec['id']) ?>/delete" class="m-0">
                                    <?= csrf_field() ?>
                                    <button class="text-xs font-medium text-rose-500 hover:text-rose-700">Delete</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
