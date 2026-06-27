<?php $this->extends('layouts.guest'); ?>
<?php $this->section('content'); ?>
<?php
/** @var array<string,mixed> $workspace */
/** @var array<string,mixed> $job */
/** @var bool $duplicate */
$base = 'careers/' . $workspace['slug'];
?>
<div class="mx-auto flex min-h-screen w-full max-w-xl items-center px-4 py-10">
    <div class="w-full rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-full bg-success-100 text-success-700 dark:bg-success-900/40 dark:text-success-300">
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </span>

        <h1 class="mt-5 text-2xl font-bold text-slate-900 dark:text-white">
            <?= $duplicate ? 'You have already applied' : 'Application received' ?>
        </h1>
        <p class="mt-2 text-slate-600 dark:text-slate-400">
            <?php if ($duplicate): ?>
                Our records show an application from you for
                <strong><?= e($job['title']) ?></strong> at <?= e($workspace['name']) ?>.
                There is no need to apply again — the team already has it.
            <?php else: ?>
                Thank you for applying for <strong><?= e($job['title']) ?></strong>
                at <?= e($workspace['name']) ?>. The hiring team will review your
                application and be in touch if it is a match.
            <?php endif; ?>
        </p>

        <div class="mt-6 flex flex-col justify-center gap-2 sm:flex-row">
            <a href="<?= e(url($base)) ?>" class="btn-primary">View other roles</a>
            <a href="<?= e(url($base . '/' . $job['slug'])) ?>" class="btn-secondary">Back to this role</a>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
