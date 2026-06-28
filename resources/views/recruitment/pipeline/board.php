<?php
/** @var array<string, list<array<string,mixed>>> $board */
/** @var array<string,string> $statuses */
/** @var bool $canManage */
/** @var string|null $status */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Pipeline</h1>
    <p class="mt-1 text-sm text-slate-500">Every applicant by hiring stage. The AI recommends; you decide.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="flex gap-4 overflow-x-auto pb-4">
    <?php foreach ($statuses as $key => $label): ?>
        <?php $cards = $board[$key] ?? []; ?>
        <div class="w-72 shrink-0">
            <div class="mb-2 flex items-center justify-between px-1">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e($label) ?></span>
                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600"><?= count($cards) ?></span>
            </div>
            <div class="space-y-2 rounded-2xl bg-slate-100/70 p-2 min-h-[6rem]">
                <?php foreach ($cards as $c): ?>
                    <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                        <a href="/candidates/<?= e($c['user_id']) ?>" class="text-sm font-medium text-indigo-600 hover:underline"><?= e($c['name']) ?></a>
                        <div class="text-xs text-slate-400"><?= e($c['job_title']) ?></div>
                        <?php if ($canManage): ?>
                            <form method="post" action="/applications/<?= e($c['id']) ?>/status" class="mt-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="redirect_to" value="/pipeline">
                                <select name="status" onchange="this.form.submit()" class="w-full rounded border border-slate-300 px-2 py-1 text-xs">
                                    <?php foreach ($statuses as $sk => $sl): ?>
                                        <option value="<?= e($sk) ?>" <?= $sk === $key ? 'selected' : '' ?>><?= e($sl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($cards === []): ?><p class="px-2 py-3 text-center text-xs text-slate-400">—</p><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
