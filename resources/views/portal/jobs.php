<?php
/** @var list<array<string,mixed>> $jobs */
/** @var array{employment_type:list<string>,seniority:list<string>,location:list<string>} $facets */
/** @var array{q:string,employment_type:string,seniority:string,location:string} $filters */
/** @var string $workspaceName */
/** @var string|null $status */
$sel = 'rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
$hasFilters = $filters['q'] !== '' || $filters['employment_type'] !== '' || $filters['seniority'] !== '' || $filters['location'] !== '';
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Available jobs</h1>
    <p class="mt-1 text-sm text-slate-500">Open roles at <span class="font-medium text-slate-700"><?= e($workspaceName) ?></span>.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<form method="get" action="/open-jobs" class="mb-5 flex flex-wrap items-end gap-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="grow">
        <label class="mb-1 block text-xs font-medium text-slate-600">Search</label>
        <input name="q" value="<?= e($filters['q']) ?>" placeholder="Title, location or keyword…" class="<?= $sel ?> w-full">
    </div>
    <?php
    $facetLabels = ['employment_type' => 'Type', 'seniority' => 'Seniority', 'location' => 'Location'];
    foreach ($facetLabels as $key => $label): ?>
        <?php if ($facets[$key] !== []): ?>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600"><?= e($label) ?></label>
                <select name="<?= e($key) ?>" class="<?= $sel ?>">
                    <option value="">All</option>
                    <?php foreach ($facets[$key] as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $filters[$key] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Filter</button>
    <?php if ($hasFilters): ?><a href="/open-jobs" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Clear</a><?php endif; ?>
</form>

<p class="mb-3 text-xs text-slate-400"><?= count($jobs) ?> open role<?= count($jobs) === 1 ? '' : 's' ?><?= $hasFilters ? ' matching your filters' : '' ?>.</p>

<?php if ($jobs === []): ?>
    <div class="rounded-2xl border border-slate-200 bg-white px-5 py-10 text-center text-sm text-slate-400 shadow-sm"><?= $hasFilters ? 'No roles match these filters. Try clearing them.' : 'No open jobs right now. Check back soon.' ?></div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($jobs as $j): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-slate-900"><?= e($j['title']) ?></h2>
                        <?php $dl = ! empty($j['deadline_at']) ? strtotime((string) $j['deadline_at'] . ' UTC') : null; ?>
                        <div class="mt-1 text-xs text-slate-400">
                            <?php if (! empty($j['location'])): ?><?= e($j['location']) ?><?php endif; ?>
                            <?php if (! empty($j['employment_type'])): ?> · <?= e($j['employment_type']) ?><?php endif; ?>
                            <?php if (! empty($j['seniority'])): ?> · <?= e($j['seniority']) ?><?php endif; ?>
                            <?php if ($dl): ?> · <span class="font-medium text-amber-600">closes in <span data-countdown="<?= e((string) max(0, $dl - time())) ?>">…</span></span><?php endif; ?>
                        </div>
                        <?php if (! empty($j['description'])): ?>
                            <p class="mt-2 line-clamp-3 text-sm text-slate-600"><?= e(mb_substr((string) $j['description'], 0, 240)) ?><?= mb_strlen((string) $j['description']) > 240 ? '…' : '' ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="shrink-0 text-right">
                        <?php if ((int) ($j['has_applied'] ?? 0) > 0): ?>
                            <span class="inline-block rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-500">Applied ✓</span>
                        <?php else: ?>
                            <?php if ($dl !== null && $dl <= time()): ?>
                                <span class="inline-block rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-500">Applications closed</span>
                            <?php else: ?>
                                <form method="post" action="/open-jobs/<?= e($j['id']) ?>/apply" enctype="multipart/form-data" class="w-56 space-y-2 text-left">
                                    <?= csrf_field() ?>
                                    <label class="block text-xs font-medium text-slate-500">Attach a CV (optional, PDF/Word)
                                        <input name="cv" type="file" accept=".pdf,.doc,.docx" class="mt-1 block w-full text-xs text-slate-500 file:mr-2 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs">
                                    </label>
                                    <input name="available_from" type="text" maxlength="255" placeholder="When can you start?" class="block w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs focus:border-indigo-500 focus:outline-none">
                                    <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Apply</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
document.querySelectorAll('[data-countdown]').forEach(function (el) {
    var s = parseInt(el.getAttribute('data-countdown'), 10) || 0;
    function fmt(t) {
        if (t <= 0) return 'closed';
        var d = Math.floor(t / 86400), h = Math.floor((t % 86400) / 3600), m = Math.floor((t % 3600) / 60), sec = t % 60;
        return (d > 0 ? d + 'd ' : '') + h + 'h ' + m + 'm ' + sec + 's';
    }
    (function tick() { el.textContent = fmt(s); if (s > 0) { s--; setTimeout(tick, 1000); } })();
});
</script>
