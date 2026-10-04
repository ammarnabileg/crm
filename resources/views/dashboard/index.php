<?php
use HaHireAI\Modules\Recruitment\Domain\ApplicationStatus;

/** @var array<string,mixed> $user */
/** @var array<string,mixed>|null $workspace */
/** @var array<string,mixed> $kpi */
/** @var array<string,mixed> $subscription */
/** @var array<string,bool> $can */
/** @var bool $isSystemOwner */
/** @var list<array<string,mixed>> $myTasks */
/** @var bool $canTask */

$c = $kpi['counts'];
$f = $kpi['funnel'];
$health = $kpi['health'];
$maxFunnel = max(1, $f['applications'], $f['interviews'], $f['offers'], $f['hires']);
$kpiCard = static function (string $label, int|string $value, string $sub = '', string $accent = 'text-slate-900'): string {
    $sub = $sub !== '' ? '<div class="mt-0.5 text-xs text-slate-400">' . e($sub) . '</div>' : '';
    return '<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">'
        . '<div class="text-xs uppercase tracking-wide text-slate-400">' . e($label) . '</div>'
        . '<div class="mt-1 text-2xl font-bold ' . $accent . '">' . e((string) $value) . '</div>' . $sub . '</div>';
};
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500"><?= e($workspace['name'] ?? '') ?> · welcome, <?= e($user['name'] ?? '') ?></p>
    </div>
    <div class="flex flex-wrap gap-2">
        <?php if (! empty($can['job'])): ?><a href="/jobs/create" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ New job</a><?php endif; ?>
        <?php if (! empty($can['candidate'])): ?><a href="/candidates" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Candidates</a><?php endif; ?>
        <?php if (! empty($can['pipeline'])): ?><a href="/pipeline" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Pipeline</a><?php endif; ?>
    </div>
</div>

<!-- KPI row -->
<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    <?= $kpiCard('Employees', $c['employees']) ?>
    <?= $kpiCard('Jobs', $c['jobs_total'], $c['jobs_open'] . ' open · ' . $c['jobs_closed'] . ' closed') ?>
    <?= $kpiCard('Open jobs', $c['jobs_open']) ?>
    <?= $kpiCard('Applicants', $c['applicants']) ?>
    <?= $kpiCard('Hires', $f['hires']) ?>
    <?= $kpiCard('Needs attention', $c['needs_attention'], 'qualified, awaiting you', $c['needs_attention'] > 0 ? 'text-amber-600' : 'text-slate-900') ?>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <!-- Hiring funnel -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">Hiring funnel</h2>
            <div class="space-y-2">
                <?php foreach (['Applications' => $f['applications'], 'Interviews' => $f['interviews'], 'Offers' => $f['offers'], 'Hires' => $f['hires']] as $label => $val): ?>
                    <div class="flex items-center gap-3 text-sm">
                        <div class="w-24 shrink-0 text-slate-500"><?= e($label) ?></div>
                        <div class="h-5 grow rounded bg-slate-100"><div class="h-5 rounded bg-indigo-500" style="width: <?= (int) round(((int) $val / $maxFunnel) * 100) ?>%"></div></div>
                        <div class="w-10 shrink-0 text-right font-semibold text-slate-700"><?= e($val) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Today's interviews -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-3"><h2 class="text-sm font-semibold text-slate-900">Today’s interviews</h2></div>
            <?php if ($kpi['today_interviews'] === []): ?>
                <p class="px-5 py-5 text-sm text-slate-400">No interviews scheduled for today.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($kpi['today_interviews'] as $iv): ?>
                        <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                            <span class="text-slate-700"><?= e($iv['candidate_name']) ?> <span class="text-slate-400">· <?= e($iv['job_title']) ?></span></span>
                            <span class="text-xs text-slate-400"><span class="uppercase"><?= e($iv['type']) ?></span> · <?= e(substr((string) $iv['scheduled_at'], 11, 5)) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Recent activity -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-3"><h2 class="text-sm font-semibold text-slate-900">Recent activity</h2></div>
            <?php if ($kpi['recent_activity'] === []): ?>
                <p class="px-5 py-5 text-sm text-slate-400">No recent activity.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($kpi['recent_activity'] as $a): ?>
                        <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                            <span class="text-slate-700"><span class="font-medium text-slate-800"><?= e($a['candidate_name']) ?></span> applied to <?= e($a['job_title']) ?></span>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"><?= e(ApplicationStatus::label((string) $a['status'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-6">
        <!-- Workspace health -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-2 text-sm font-semibold text-slate-900">Workspace health</h2>
            <?php $hc = $health['score'] >= 80 ? 'text-emerald-600' : ($health['score'] >= 50 ? 'text-amber-600' : 'text-rose-600'); ?>
            <div class="text-3xl font-bold <?= $hc ?>"><?= e($health['score']) ?><span class="text-base text-slate-400">/100</span></div>
            <?php if ($health['signals'] === []): ?>
                <p class="mt-2 text-xs text-emerald-600">All good — hiring is flowing.</p>
            <?php else: ?>
                <ul class="mt-2 list-inside list-disc text-xs text-slate-500"><?php foreach ($health['signals'] as $s): ?><li><?= e($s) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>

        <!-- Subscription -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-2 text-sm font-semibold text-slate-900">Subscription</h2>
            <p class="text-sm <?= $subscription['usable'] ? 'text-emerald-600' : 'text-rose-600' ?>"><?= $subscription['usable'] ? 'Active' : 'Inactive / limited' ?></p>
            <p class="mt-0.5 text-xs text-slate-400"><?= $subscription['features'] === null ? 'No plan gating (free period)' : e($subscription['features']) . ' feature(s) enabled' ?></p>
            <a href="/billing" class="mt-2 inline-block text-xs font-medium text-indigo-600 hover:underline">Manage billing →</a>
        </div>

        <!-- AI recommendations -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-3"><h2 class="text-sm font-semibold text-slate-900">AI recommendations</h2></div>
            <?php if ($kpi['ai_recommendations'] === []): ?>
                <p class="px-5 py-5 text-sm text-slate-400">No strong AI matches yet.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($kpi['ai_recommendations'] as $rec): ?>
                        <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                            <a href="/candidates/<?= e($rec['candidate_user_id']) ?>" class="font-medium text-indigo-600 hover:underline"><?= e($rec['candidate_name']) ?></a>
                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700"><?= e($rec['fit_score']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- My tasks -->
        <?php if (! empty($canTask)): ?>
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                    <h2 class="text-sm font-semibold text-slate-900">My tasks</h2>
                    <a href="/tasks" class="text-xs font-medium text-indigo-600 hover:underline">All tasks →</a>
                </div>
                <?php if (($myTasks ?? []) === []): ?>
                    <p class="px-5 py-5 text-sm text-slate-400">No open tasks assigned to you.</p>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach ($myTasks as $t): ?>
                            <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                                <a href="/tasks" class="text-slate-700 hover:text-indigo-600"><?= e($t['title']) ?></a>
                                <span class="shrink-0 text-xs <?= ! empty($t['due_at']) ? 'text-slate-400' : 'text-slate-300' ?>"><?= ! empty($t['due_at']) ? e(substr((string) $t['due_at'], 0, 10)) : 'no due date' ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Recent jobs -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-3"><h2 class="text-sm font-semibold text-slate-900">Recent jobs</h2></div>
            <?php if ($kpi['recent_jobs'] === []): ?>
                <p class="px-5 py-5 text-sm text-slate-400">No jobs yet.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($kpi['recent_jobs'] as $j): ?>
                        <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                            <a href="/jobs/<?= e($j['id']) ?>" class="font-medium text-indigo-600 hover:underline"><?= e($j['title']) ?></a>
                            <span class="text-xs text-slate-400"><?= e($j['applicants']) ?> · <?= e($j['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
