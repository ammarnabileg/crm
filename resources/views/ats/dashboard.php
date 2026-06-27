<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * @var int $openJobs @var int $newApplications @var int $pendingReviews
 * @var int $interviewing @var int $openOffers
 * @var array<int,array<string,mixed>> $todaysInterviews
 * @var array<int,array<string,mixed>> $myTasks
 * @var array<int,array<string,mixed>> $recentJobs
 */
$stats = [
    ['Open jobs', $openJobs, 'jobs'],
    ['New applications', $newApplications, 'jobs'],
    ['Pending reviews', $pendingReviews, 'jobs'],
    ['Interviewing', $interviewing, 'jobs'],
    ['Offers out', $openOffers, 'jobs'],
];
?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Recruiter workspace</h1>
        <a href="<?= e(url('jobs')) ?>" class="btn-primary">Manage jobs</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <?php foreach ($stats as [$label, $value, $link]): ?>
            <a href="<?= e(url($link)) ?>" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:ring-indigo-300">
                <div class="text-sm text-slate-500"><?= e($label) ?></div>
                <div class="mt-1 text-3xl font-semibold text-slate-900"><?= e((string) $value) ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-3 text-lg font-semibold text-slate-900">Today's interviews</h2>
            <?php if ($todaysInterviews === []): ?>
                <p class="text-sm text-slate-500">No interviews scheduled.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($todaysInterviews as $m): ?>
                        <li class="flex items-center justify-between py-2 text-sm">
                            <span class="text-slate-700"><?= e((string) ($m['title'] ?? 'Interview')) ?></span>
                            <span class="text-slate-500"><?= e((string) ($m['starts_at'] ?? '')) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-3 text-lg font-semibold text-slate-900">My tasks</h2>
            <?php if ($myTasks === []): ?>
                <p class="text-sm text-slate-500">No open tasks.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($myTasks as $t): ?>
                        <li class="flex items-center justify-between py-2 text-sm">
                            <span class="text-slate-700"><?= e((string) $t['title']) ?></span>
                            <span class="badge-amber"><?= e((string) $t['priority']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-3 text-lg font-semibold text-slate-900">Recent jobs</h2>
        <?php if ($recentJobs === []): ?>
            <p class="text-sm text-slate-500">No jobs yet. <a class="text-indigo-600" href="<?= e(url('jobs')) ?>">Create one</a>.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($recentJobs as $j): ?>
                    <li class="flex items-center justify-between py-2 text-sm">
                        <a class="text-indigo-600 hover:underline" href="<?= e(url('jobs/board?job=' . $j['id'])) ?>"><?= e((string) $j['title']) ?></a>
                        <span class="text-slate-500"><?= e((string) ($j['created_at'] ?? '')) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php $this->endSection(); ?>
