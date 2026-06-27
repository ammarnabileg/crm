<?php $this->extends('layouts.guest'); ?>
<?php $this->section('content'); ?>
<?php
/** @var array<string,mixed> $workspace */
/** @var array<int,array<string,mixed>> $jobs */
/** @var string $keyword */
$base = 'careers/' . $workspace['slug'];
?>
<div class="mx-auto w-full max-w-4xl px-4 py-10 sm:py-14">
    <header class="mb-8 flex items-center gap-4">
        <?php if (! empty($workspace['logo'])): ?>
            <img src="<?= e($workspace['logo']) ?>" alt="<?= e($workspace['name']) ?>" class="h-14 w-14 rounded-2xl object-cover shadow">
        <?php else: ?>
            <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-600 text-2xl font-bold text-white shadow">
                <?= e(mb_strtoupper(mb_substr((string) $workspace['name'], 0, 1))) ?>
            </span>
        <?php endif; ?>
        <div>
            <p class="text-sm font-medium text-brand-600 dark:text-brand-400">Careers at</p>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl"><?= e($workspace['name']) ?></h1>
        </div>
    </header>

    <form method="GET" action="<?= e(url($base)) ?>" class="mb-8" role="search">
        <div class="flex gap-2">
            <input class="input flex-1" type="search" name="q" value="<?= e($keyword) ?>"
                   placeholder="Search open roles…" aria-label="Search open roles">
            <button type="submit" class="btn-primary">Search</button>
        </div>
    </form>

    <?php if ($jobs === []): ?>
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white/60 p-10 text-center dark:border-slate-700 dark:bg-slate-900/40">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                <?= $keyword !== '' ? 'No roles match your search' : 'No open positions right now' ?>
            </h2>
            <p class="mt-1 text-slate-600 dark:text-slate-400">
                <?= $keyword !== ''
                    ? 'Try a different keyword, or check back soon.'
                    : 'There are no published openings at the moment. Please check back soon.' ?>
            </p>
            <?php if ($keyword !== ''): ?>
                <a href="<?= e(url($base)) ?>" class="btn-secondary mt-4 inline-flex">View all roles</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
            <?= count($jobs) ?> open <?= count($jobs) === 1 ? 'position' : 'positions' ?>
        </p>
        <ul class="space-y-4">
            <?php foreach ($jobs as $job): ?>
                <li>
                    <a href="<?= e(url($base . '/' . $job['slug'])) ?>"
                       class="group block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-brand-300 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-brand-700">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-400">
                                    <?= e($job['title']) ?>
                                </h3>
                                <?php if ($job['summary'] !== ''): ?>
                                    <p class="mt-1 line-clamp-2 text-sm text-slate-600 dark:text-slate-400"><?= e($job['summary']) ?></p>
                                <?php endif; ?>
                            </div>
                            <svg class="mt-1 h-5 w-5 shrink-0 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-brand-600 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <?php if ($job['department'] !== null): ?>
                                <span class="badge"><?= e($job['department']) ?></span>
                            <?php endif; ?>
                            <?php if ($job['employment_type'] !== null): ?>
                                <span class="badge"><?= e($job['employment_type']) ?></span>
                            <?php endif; ?>
                            <?php if ($job['is_remote']): ?>
                                <span class="badge badge-success">Remote</span>
                            <?php endif; ?>
                            <?php if ($job['salary'] !== null): ?>
                                <span class="badge"><?= e($job['salary']) ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <footer class="mt-12 border-t border-slate-200 pt-6 text-center text-xs text-slate-400 dark:border-slate-800">
        Powered by <?= e(config('app.name')) ?>
    </footer>
</div>
<?php $this->endSection(); ?>
