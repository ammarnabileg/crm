<?php
/** @var list<array<string,mixed>> $interviews */
/** @var string|null $status */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Interviews</h1>
    <p class="mt-1 text-sm text-slate-500">AI and human interviews across this workspace. Every result is advisory — a human decision always wins.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <?php if ($interviews === []): ?>
        <p class="px-5 py-6 text-sm text-slate-400">No interviews yet. Schedule one from a candidate profile.</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($interviews as $iv): ?>
                <li class="flex items-center justify-between px-5 py-3 text-sm">
                    <div>
                        <span class="rounded bg-slate-200 px-1.5 py-0.5 text-xs font-medium uppercase text-slate-600"><?= e($iv['type']) ?></span>
                        <a href="/candidates/<?= e($iv['candidate_user_id']) ?>" class="font-medium text-indigo-600 hover:underline"><?= e($iv['candidate_name']) ?></a>
                        <span class="text-slate-400">· <?= e($iv['job_title']) ?></span>
                        <div class="text-xs text-slate-400"><?= e($iv['status']) ?><?php if (! empty($iv['scheduled_at'])): ?> · <?= e($iv['scheduled_at']) ?> UTC<?php endif; ?></div>
                    </div>
                    <div class="text-right">
                        <?php if ($iv['score'] !== null): ?>
                            <?php $sc = (int) $iv['score'] >= 75 ? 'text-emerald-700' : ((int) $iv['score'] >= 55 ? 'text-amber-700' : 'text-rose-700'); ?>
                            <span class="text-sm font-semibold <?= $sc ?>"><?= e($iv['score']) ?>/100</span>
                            <div class="text-xs text-slate-400"><?= e($iv['recommendation'] ?? '') ?></div>
                        <?php else: ?>
                            <span class="text-xs text-slate-400">pending</span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
