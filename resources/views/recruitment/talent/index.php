<?php
/** @var list<array<string,mixed>> $pools */
/** @var list<array<string,mixed>> $smartLists */
/** @var bool $canManage */
/** @var string|null $status */
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Talent Pool</h1>
        <p class="mt-1 text-sm text-slate-500">Saved candidates you may want for future roles, plus smart lists for re-engagement.</p>
    </div>
    <a href="/talent-pool/segments" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-50">Smart Segments →</a>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<!-- Smart lists (auto-computed segments) -->
<?php if (($smartLists ?? []) !== []): ?>
    <div class="mb-6 space-y-3">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Smart lists</h2>
        <?php foreach ($smartLists as $list): ?>
            <details class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <summary class="flex cursor-pointer items-center justify-between px-5 py-3">
                    <span>
                        <span class="text-sm font-semibold text-slate-900"><?= e($list['label']) ?></span>
                        <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"><?= count($list['candidates']) ?></span>
                        <span class="block text-xs text-slate-400"><?= e($list['description']) ?></span>
                    </span>
                </summary>
                <?php if ($list['candidates'] === []): ?>
                    <p class="px-5 pb-4 text-sm text-slate-400">No candidates match yet.</p>
                <?php else: ?>
                    <form method="post" action="/talent-pool/bulk-add" class="px-5 pb-4">
                        <?= csrf_field() ?>
                        <ul class="mb-3 divide-y divide-slate-100">
                            <?php foreach ($list['candidates'] as $cand): ?>
                                <li class="flex items-center justify-between py-2 text-sm">
                                    <label class="flex items-center gap-2">
                                        <?php if ($canManage): ?><input type="checkbox" name="candidate_user_ids[]" value="<?= e($cand['user_id']) ?>" checked class="rounded border-slate-300"><?php endif; ?>
                                        <a href="/candidates/<?= e($cand['user_id']) ?>" class="font-medium text-indigo-600 hover:underline"><?= e($cand['name']) ?></a>
                                        <span class="text-xs text-slate-400"><?= e($cand['email']) ?></span>
                                    </label>
                                    <span class="text-xs text-slate-400"><?= e($cand['signal'] ?? '') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($canManage && $pools !== []): ?>
                            <div class="flex items-center gap-2">
                                <select name="pool_id" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                                    <?php foreach ($pools as $p): ?><option value="<?= e($p['id']) ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
                                </select>
                                <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700">Add selected to pool</button>
                            </div>
                        <?php elseif ($canManage): ?>
                            <p class="text-xs text-slate-400">Create a pool below to save these candidates.</p>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </details>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

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
