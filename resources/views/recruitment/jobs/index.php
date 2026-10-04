<?php
/** @var list<array<string,mixed>> $jobs */
/** @var array{q:string,status:string} $filters */
/** @var bool $canCreate */
/** @var string|null $status */
$sel = static fn (string $v): string => ($filters['status'] ?? '') === $v ? 'selected' : '';
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Jobs</h1>
        <p class="mt-1 text-sm text-slate-500"><?= count($jobs) ?> job(s)</p>
    </div>
    <?php if ($canCreate): ?>
        <a href="/jobs/create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ New job</a>
    <?php endif; ?>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<form method="get" action="/jobs" class="mb-4 flex flex-wrap gap-2">
    <input name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Search jobs by title…" class="w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Any status</option>
        <?php foreach (['draft' => 'Draft', 'published' => 'Published', 'paused' => 'Paused', 'closed' => 'Closed', 'archived' => 'Archived'] as $v => $l): ?>
            <option value="<?= $v ?>" <?= $sel($v) ?>><?= $l ?></option>
        <?php endforeach; ?>
    </select>
    <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Filter</button>
    <?php if (($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== ''): ?><a href="/jobs" class="rounded-lg px-3 py-2 text-sm text-slate-400 hover:text-slate-600">Clear</a><?php endif; ?>
</form>

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <?php if ($jobs === []): ?>
        <p class="px-5 py-8 text-center text-sm text-slate-400">No jobs yet. Create your first job to start hiring.</p>
    <?php else: ?>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                <tr><th class="px-5 py-3">Title</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Applications</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($jobs as $job): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3"><a href="/jobs/<?= e($job['id']) ?>" class="font-medium text-indigo-600 hover:underline"><?= e($job['title']) ?></a></td>
                        <td class="px-5 py-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"><?= e($job['status']) ?></span></td>
                        <td class="px-5 py-3 text-slate-600"><?= e($job['applications_count']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
