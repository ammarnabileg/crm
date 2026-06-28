<?php
/** @var array<string,mixed> $report */
/** @var bool $canExport */

$r = $report;
$money = static fn (int $c): string => '$' . number_format($c / 100, 2);
$card = static function (string $label, string $value, string $sub = ''): string {
    $sub = $sub !== '' ? '<div class="mt-0.5 text-xs text-slate-400">' . e($sub) . '</div>' : '';

    return '<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">'
        . '<div class="text-xs uppercase tracking-wide text-slate-400">' . e($label) . '</div>'
        . '<div class="mt-1 text-2xl font-bold text-slate-900">' . e($value) . '</div>' . $sub . '</div>';
};

$f = $r['funnel'];
$stages = [
    ['Published jobs', $r['jobs']['published']],
    ['Applications', $f['applications']],
    ['Interviews', $f['interviews']],
    ['Offers', $f['offers']],
    ['Hires', $f['hires']],
];
$max = max(1, $f['applications'], $r['jobs']['published'], $f['interviews'], $f['offers'], $f['hires']);
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Reports</h1>
        <p class="mt-1 text-sm text-slate-500">Your recruitment funnel and activity in this workspace.</p>
    </div>
    <div class="flex gap-2">
        <a href="/reports/print" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">Print / PDF</a>
        <?php if ($canExport): ?>
            <a href="/reports/export" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">Export CSV</a>
        <?php endif; ?>
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-4">
    <?= $card('Published jobs', (string) $r['jobs']['published'], $r['jobs']['total'] . ' total') ?>
    <?= $card('Applications', (string) $f['applications']) ?>
    <?= $card('Interviews', (string) $r['interviews']['completed'] . ' done', $r['interviews']['avg_score'] !== null ? 'avg score ' . $r['interviews']['avg_score'] : 'no scores') ?>
    <?= $card('Hires', (string) $f['hires'], $r['offers']['accepted'] . ' offers accepted') ?>
</div>

<div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="mb-4 text-sm font-semibold text-slate-900">Hiring funnel</h2>
    <div class="space-y-2">
        <?php foreach ($stages as [$label, $value]): ?>
            <div class="flex items-center gap-3 text-sm">
                <div class="w-28 shrink-0 text-slate-500"><?= e($label) ?></div>
                <div class="h-5 grow rounded bg-slate-100">
                    <div class="h-5 rounded bg-indigo-500" style="width: <?= (int) round(((int) $value / $max) * 100) ?>%"></div>
                </div>
                <div class="w-10 shrink-0 text-right font-semibold text-slate-700"><?= e($value) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Applications by status</h2>
        <?php if (($r['applications_by_status'] ?? []) === []): ?>
            <p class="text-sm text-slate-400">No applications yet.</p>
        <?php else: ?>
            <ul class="space-y-1 text-sm">
                <?php foreach ($r['applications_by_status'] as $status => $count): ?>
                    <li class="flex justify-between rounded-md bg-slate-50 px-3 py-1.5">
                        <span class="capitalize text-slate-700"><?= e(str_replace('_', ' ', (string) $status)) ?></span>
                        <span class="font-semibold text-slate-800"><?= e($count) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-900">AI usage</h2>
        <p class="text-sm text-slate-600"><?= e($r['ai']['sessions']) ?> AI runs · cost <?= e($money((int) $r['ai']['cost_cents'])) ?></p>
    </div>
</div>
