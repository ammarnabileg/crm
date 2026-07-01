<?php
/** @var array<string,mixed> $fi */
$kpi = 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm';
$lbl = 'text-xs font-semibold uppercase tracking-wide text-slate-400';
$num = 'mt-1 text-3xl font-semibold text-slate-900';
$maxDist = max(1, ...array_values($fi['distribution'] ?: [1]));
$dollars = static fn (int $cents): string => '$' . number_format($cents / 100, 2);
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">First Impression analytics</h1>
        <p class="mt-1 text-sm text-slate-500">How the zero-AI gate is screening applicants — and the AI credits it saves.</p>
    </div>
    <?php if (($fi['jobs'] ?? []) !== []): ?>
        <form method="get" action="/reports/first-impression" class="flex items-end gap-2">
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-slate-600">Job</span>
                <select name="job_id" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    <option value="">All jobs</option>
                    <?php foreach ($fi['jobs'] as $j): ?>
                        <option value="<?= e((string) $j['id']) ?>" <?= ($fi['selected_job'] ?? null) === $j['id'] ? 'selected' : '' ?>><?= e((string) $j['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
    <?php endif; ?>
</div>

<?php if ((int) $fi['applicants'] === 0): ?>
    <div class="rounded-2xl border border-slate-200 bg-white px-5 py-10 text-center text-sm text-slate-400 shadow-sm">
        No First Impression reports yet. Enable the filter on a job and applicants will be screened automatically.
    </div>
<?php else: ?>
    <!-- KPI row -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="<?= $kpi ?>"><div class="<?= $lbl ?>">Applicants</div><div class="<?= $num ?>"><?= (int) $fi['applicants'] ?></div></div>
        <div class="<?= $kpi ?>"><div class="<?= $lbl ?>">Passed → AI</div><div class="<?= $num ?> text-emerald-600"><?= (int) $fi['passed'] ?></div><div class="mt-1 text-xs text-slate-400"><?= (int) $fi['pass_rate'] ?>% conversion</div></div>
        <div class="<?= $kpi ?>"><div class="<?= $lbl ?>">Filtered before AI</div><div class="<?= $num ?> text-rose-600"><?= (int) $fi['filtered'] ?></div><div class="mt-1 text-xs text-slate-400"><?= (int) $fi['overridden'] ?> overridden</div></div>
        <div class="<?= $kpi ?>"><div class="<?= $lbl ?>">Average score</div><div class="<?= $num ?>"><?= (int) $fi['avg_score'] ?>%</div><div class="mt-1 text-xs text-slate-400">CV <?= (int) $fi['avg_resume'] ?>% · fit <?= (int) $fi['avg_job_match'] ?>%</div></div>
    </div>

    <!-- Credits saved -->
    <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="<?= $lbl ?> text-emerald-700">AI credits saved</div>
                <p class="mt-1 text-sm text-emerald-800">Filtering applicants before the AI interview avoided paid model usage.</p>
            </div>
            <div class="flex gap-8 text-right">
                <div><div class="text-2xl font-bold text-emerald-700"><?= (int) $fi['ai_interviews_avoided'] ?></div><div class="text-xs text-emerald-600">interviews avoided</div></div>
                <div><div class="text-2xl font-bold text-emerald-700"><?= number_format((int) $fi['est_tokens_saved']) ?></div><div class="text-xs text-emerald-600">est. tokens saved</div></div>
                <div><div class="text-2xl font-bold text-emerald-700"><?= $dollars((int) $fi['est_cost_cents_saved']) ?></div><div class="text-xs text-emerald-600">est. cost saved</div></div>
            </div>
        </div>
        <p class="mt-2 text-[11px] text-emerald-600/80">Estimate: ~9,000 tokens per avoided AI interview at the engine's accounting rate. Actual provider pricing may differ.</p>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <!-- Distribution -->
        <div class="<?= $kpi ?>">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Score distribution</h2>
            <?php foreach ($fi['distribution'] as $band => $count): ?>
                <div class="mb-2">
                    <div class="flex justify-between text-xs"><span class="text-slate-600"><?= e((string) $band) ?></span><span class="font-semibold text-slate-800"><?= (int) $count ?></span></div>
                    <div class="mt-1 h-2 w-full rounded-full bg-slate-100"><div class="h-2 rounded-full bg-indigo-500" style="width:<?= (int) round($count / $maxDist * 100) ?>%"></div></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Funnel -->
        <div class="<?= $kpi ?>">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Hiring funnel</h2>
            <?php $top = max(1, (int) ($fi['funnel'][0]['value'] ?? 1)); ?>
            <?php foreach ($fi['funnel'] as $step): ?>
                <div class="mb-2">
                    <div class="flex justify-between text-xs"><span class="text-slate-600"><?= e((string) $step['label']) ?></span><span class="font-semibold text-slate-800"><?= (int) $step['value'] ?></span></div>
                    <div class="mt-1 h-2 w-full rounded-full bg-slate-100"><div class="h-2 rounded-full bg-emerald-500" style="width:<?= (int) round((int) $step['value'] / $top * 100) ?>%"></div></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Top matched skills -->
        <div class="<?= $kpi ?>">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Top matched skills</h2>
            <?php if ($fi['top_matched'] === []): ?><p class="text-xs text-slate-400">—</p><?php else: ?>
                <?php foreach ($fi['top_matched'] as $s): ?>
                    <span class="mr-1 mb-1 inline-block rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs text-emerald-700"><?= e((string) $s['label']) ?> · <?= (int) $s['count'] ?></span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Most missing skills -->
        <div class="<?= $kpi ?>">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Most missing skills</h2>
            <?php if ($fi['top_missing'] === []): ?><p class="text-xs text-slate-400">—</p><?php else: ?>
                <?php foreach ($fi['top_missing'] as $s): ?>
                    <span class="mr-1 mb-1 inline-block rounded-full bg-rose-50 px-2.5 py-0.5 text-xs text-rose-700"><?= e((string) $s['label']) ?> · <?= (int) $s['count'] ?></span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Common weaknesses + monthly -->
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div class="<?= $kpi ?>">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Most common weaknesses</h2>
            <?php if ($fi['top_weaknesses'] === []): ?><p class="text-xs text-slate-400">—</p><?php else: ?>
                <ul class="space-y-1">
                    <?php foreach ($fi['top_weaknesses'] as $w): ?>
                        <li class="flex justify-between text-xs text-slate-600"><span class="pr-2"><?= e((string) $w['label']) ?></span><span class="shrink-0 font-semibold text-slate-800"><?= (int) $w['count'] ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="<?= $kpi ?>">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">By month</h2>
            <?php if ($fi['by_month'] === []): ?><p class="text-xs text-slate-400">—</p><?php else: ?>
                <table class="w-full text-xs">
                    <thead><tr class="text-slate-400"><th class="text-left font-medium">Month</th><th class="text-right font-medium">Applicants</th><th class="text-right font-medium">Passed</th><th class="text-right font-medium">Filtered</th><th class="text-right font-medium">Avg</th></tr></thead>
                    <tbody>
                        <?php foreach ($fi['by_month'] as $m): ?>
                            <tr class="border-t border-slate-100 text-slate-600">
                                <td class="py-1"><?= e((string) $m['month']) ?></td>
                                <td class="py-1 text-right"><?= (int) $m['applicants'] ?></td>
                                <td class="py-1 text-right text-emerald-600"><?= (int) $m['passed'] ?></td>
                                <td class="py-1 text-right text-rose-600"><?= (int) $m['filtered'] ?></td>
                                <td class="py-1 text-right font-semibold text-slate-800"><?= (int) $m['avg_score'] ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
