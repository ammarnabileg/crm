<?php
/** @var list<array<string,mixed>> $interviews */
/** @var string $query */
/** @var string $statusFilter */
/** @var string|null $status */
?>
<div class="mb-6 flex items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">AI Interviews</h1>
        <p class="mt-1 text-sm text-slate-500">AI-run screening interviews across this workspace. Every result is advisory — a human decision always wins. For panel interviews, see <a href="/human-interviews" class="text-indigo-600 hover:underline">Human Interviews</a>.</p>
    </div>
    <a href="/interviews/export" class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Export Excel (.xlsx)</a>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<form method="get" action="/interviews" class="mb-4 flex flex-wrap gap-2">
    <input name="q" value="<?= e($query ?? '') ?>" placeholder="Search by candidate or job…" class="w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Any status</option>
        <?php foreach (['scheduled' => 'Scheduled', 'in_progress' => 'In progress', 'completed' => 'Completed'] as $v => $l): ?>
            <option value="<?= $v ?>" <?= ($statusFilter ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
    </select>
    <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Filter</button>
    <?php if (($query ?? '') !== '' || ($statusFilter ?? '') !== ''): ?><a href="/interviews" class="rounded-lg px-3 py-2 text-sm text-slate-400 hover:text-slate-600">Clear</a><?php endif; ?>
</form>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <?php if ($interviews === []): ?>
        <p class="px-5 py-6 text-sm text-slate-400">No interviews match. Schedule one from a candidate profile.</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($interviews as $iv): ?>
                <li class="flex items-center justify-between px-5 py-3 text-sm">
                    <div>
                        <span class="rounded bg-slate-200 px-1.5 py-0.5 text-xs font-medium uppercase text-slate-600"><?= e($iv['mode'] ?? $iv['type']) ?></span>
                        <a href="/interviews/<?= e($iv['id']) ?>" class="font-medium text-indigo-600 hover:underline"><?= e($iv['candidate_name']) ?></a>
                        <span class="text-slate-400">· <?= e($iv['job_title']) ?></span>
                        <div class="text-xs text-slate-400"><?= e($iv['status']) ?><?php if (! empty($iv['ai_provider'])): ?> · <?= e($iv['ai_provider']) ?><?php endif; ?><?php if (! empty($iv['scheduled_at'])): ?> · <?= e($iv['scheduled_at']) ?> UTC<?php endif; ?></div>
                    </div>
                    <div class="text-right">
                        <?php if ($iv['score'] !== null): ?>
                            <?php $sc = (int) $iv['score'] >= 75 ? 'text-emerald-700' : ((int) $iv['score'] >= 55 ? 'text-amber-700' : 'text-rose-700'); ?>
                            <span class="text-sm font-semibold <?= $sc ?>"><?= e($iv['score']) ?>/100</span>
                            <div class="text-xs text-slate-400"><?= e($iv['recommendation'] ?? '') ?></div>
                        <?php else: ?>
                            <span class="text-xs text-slate-400">pending</span>
                        <?php endif; ?>
                        <a href="/interviews/<?= e($iv['id']) ?>" class="ml-2 text-xs font-medium text-slate-400 hover:text-slate-600">Report →</a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
