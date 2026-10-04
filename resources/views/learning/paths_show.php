<?php
/**
 * @var array<string,mixed> $path
 * @var list<array<string,mixed>> $programs
 * @var array{total:int,completed:int,percent:int} $progress
 * @var list<array<string,mixed>> $available
 * @var bool $canManage
 * @var string|null $status
 */
$id = (string) $path['id'];
?>
<div class="mb-4"><a href="/learning-paths" class="text-sm text-slate-500 hover:text-slate-700">← All paths</a></div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900"><?= e($path['title']) ?></h1>
            <?php if (! empty($path['description'])): ?><p class="mt-1 text-sm text-slate-500"><?= e($path['description']) ?></p><?php endif; ?>
        </div>
        <?php if ($canManage): ?>
            <div class="flex items-center gap-2">
                <?php foreach (['draft' => 'Draft', 'published' => 'Publish', 'archived' => 'Archive'] as $s => $label): ?>
                    <?php if ((string) $path['status'] !== $s): ?>
                        <form method="post" action="/learning-paths/<?= e($id) ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="<?= $s ?>"><button class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"><?= $label ?></button></form>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="mt-4 flex items-center gap-3">
        <div class="h-2 grow rounded-full bg-slate-100"><div class="h-2 rounded-full bg-indigo-500" style="width:<?= max(2, (int) $progress['percent']) ?>%"></div></div>
        <span class="text-xs font-medium text-slate-600">Your progress: <?= (int) $progress['completed'] ?>/<?= (int) $progress['total'] ?> (<?= (int) $progress['percent'] ?>%)</span>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-3 lg:col-span-2">
        <?php if ($programs === []): ?>
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-10 text-center text-sm text-slate-400 shadow-sm">No programs in this path yet.</div>
        <?php else: ?>
            <?php foreach ($programs as $i => $p): ?>
                <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div>
                        <span class="text-slate-400"><?= $i + 1 ?>.</span>
                        <a href="/my-learning/<?= e($p['program_id']) ?>" class="font-medium text-slate-800 hover:text-indigo-700"><?= e($p['title']) ?></a>
                        <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500"><?= e($p['status']) ?></span>
                    </div>
                    <?php if ($canManage): ?>
                        <form method="post" action="/learning-paths/<?= e($id) ?>/programs/<?= e($p['program_id']) ?>/delete"><?= csrf_field() ?><button class="text-xs text-slate-400 hover:text-rose-600">remove</button></form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
        <div class="self-start rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Add a program</h2>
            <form method="post" action="/learning-paths/<?= e($id) ?>/programs" class="space-y-2">
                <?= csrf_field() ?>
                <select name="program_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Choose a program…</option>
                    <?php foreach ($available as $a): ?><option value="<?= e($a['id']) ?>"><?= e($a['title']) ?></option><?php endforeach; ?>
                </select>
                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add to path</button>
            </form>
        </div>
    <?php endif; ?>
</div>
