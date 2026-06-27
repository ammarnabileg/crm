<?php $this->extends('layouts.guest'); ?>
<?php $this->section('content'); ?>
<?php
/** @var array<string,mixed> $workspace */
/** @var array<string,mixed> $job */
$base = 'careers/' . $workspace['slug'];
?>
<div class="mx-auto w-full max-w-3xl px-4 py-10 sm:py-14">
    <a href="<?= e(url($base)) ?>" class="mb-6 inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">
        <svg class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        All roles at <?= e($workspace['name']) ?>
    </a>

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl"><?= e($job['title']) ?></h1>
        <div class="mt-3 flex flex-wrap gap-2">
            <?php if ($job['department'] !== null): ?>
                <span class="badge"><?= e($job['department']) ?></span>
            <?php endif; ?>
            <?php if ($job['employment_type'] !== null): ?>
                <span class="badge"><?= e($job['employment_type']) ?></span>
            <?php endif; ?>
            <?php if (! empty($job['experience_level'])): ?>
                <span class="badge"><?= e($job['experience_level']) ?></span>
            <?php endif; ?>
            <?php if ($job['is_remote']): ?>
                <span class="badge badge-success">Remote</span>
            <?php endif; ?>
            <?php if ($job['salary'] !== null): ?>
                <span class="badge"><?= e($job['salary']) ?></span>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($job['summary'] !== ''): ?>
        <p class="mb-6 text-lg text-slate-700 dark:text-slate-300"><?= e($job['summary']) ?></p>
    <?php endif; ?>

    <?php if (($job['description'] ?? '') !== ''): ?>
        <div class="prose prose-slate mb-10 max-w-none text-slate-700 dark:text-slate-300">
            <?= nl2br(e($job['description'])) ?>
        </div>
    <?php endif; ?>

    <section id="apply" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Apply for this role</h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Tell us a little about yourself. Fields marked * are required.</p>

        <?php $this->include('partials.alerts'); ?>

        <form method="POST" action="<?= e(url($base . '/' . $job['slug'] . '/apply')) ?>" enctype="multipart/form-data" class="mt-4 space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="label" for="name">Full name *</label>
                <input class="input" id="name" name="name" type="text" value="<?= e(old('name')) ?>" maxlength="120" required autocomplete="name">
            </div>
            <div>
                <label class="label" for="email">Email *</label>
                <input class="input" id="email" name="email" type="email" value="<?= e(old('email')) ?>" maxlength="190" required autocomplete="email">
            </div>
            <div>
                <label class="label" for="cover_letter">Cover letter <span class="text-slate-400">(optional)</span></label>
                <textarea class="input" id="cover_letter" name="cover_letter" rows="5" maxlength="5000" placeholder="Why are you a great fit for this role?"><?= e(old('cover_letter')) ?></textarea>
            </div>
            <div>
                <label class="label" for="cv">CV / résumé <span class="text-slate-400">(optional)</span></label>
                <input class="input" id="cv" name="cv" type="file" accept=".pdf,.doc,.docx,.rtf,.txt,.odt">
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">PDF or Word document, up to 10&nbsp;MB.</p>
            </div>
            <button type="submit" class="btn-primary w-full sm:w-auto">Submit application</button>
        </form>
    </section>

    <footer class="mt-12 border-t border-slate-200 pt-6 text-center text-xs text-slate-400 dark:border-slate-800">
        Powered by <?= e(config('app.name')) ?>
    </footer>
</div>
<?php $this->endSection(); ?>
