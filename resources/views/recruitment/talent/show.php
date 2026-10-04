<?php
/** @var array<string,mixed> $pool */
/** @var list<array<string,mixed>> $members */
/** @var bool $canManage */
?>
<div class="mb-6">
    <a href="/talent-pool" class="text-sm text-indigo-600 hover:underline">&larr; Talent Pool</a>
    <h1 class="mt-1 text-2xl font-semibold text-slate-900"><?= e($pool['name']) ?></h1>
    <?php if (! empty($pool['description'])): ?><p class="text-sm text-slate-500"><?= e($pool['description']) ?></p><?php endif; ?>
</div>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <?php if ($members === []): ?>
        <p class="px-5 py-6 text-sm text-slate-400">No candidates saved here yet. Use “Save to pool” on a candidate profile.</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($members as $m): ?>
                <li class="flex items-center justify-between px-5 py-3 text-sm">
                    <div>
                        <a href="/candidates/<?= e($m['user_id']) ?>" class="font-medium text-indigo-600 hover:underline"><?= e($m['name']) ?></a>
                        <span class="text-slate-400">· <?= e($m['email']) ?></span>
                        <?php if (! empty($m['note'])): ?><div class="text-xs text-slate-400"><?= e($m['note']) ?></div><?php endif; ?>
                    </div>
                    <?php if ($canManage): ?>
                        <form method="post" action="/talent-pool/<?= e($pool['id']) ?>/remove">
                            <?= csrf_field() ?>
                            <input type="hidden" name="candidate_user_id" value="<?= e($m['user_id']) ?>">
                            <input type="hidden" name="redirect_to" value="/talent-pool/<?= e($pool['id']) ?>">
                            <button class="text-xs font-medium text-rose-600 hover:text-rose-700">Remove</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
