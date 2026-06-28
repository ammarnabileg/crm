<?php
/** @var array<string,mixed> $job */
/** @var list<array<string,mixed>> $stages */
/** @var bool $canPublish */
/** @var bool $canViewPipeline */
/** @var string|null $status */
?>
<div class="mb-6 flex items-start justify-between">
    <div>
        <a href="/jobs" class="text-sm text-indigo-600 hover:underline">&larr; Jobs</a>
        <h1 class="mt-1 text-2xl font-semibold text-slate-900"><?= e($job['title']) ?></h1>
        <p class="mt-1 text-sm text-slate-500">
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"><?= e($job['status']) ?></span>
            <?= $job['location'] ? '· ' . e($job['location']) : '' ?>
        </p>
    </div>
    <div class="flex gap-2">
        <?php if ($canViewPipeline && $job['status'] !== 'draft'): ?>
            <a href="/jobs/<?= e($job['id']) ?>/pipeline" class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Pipeline</a>
        <?php endif; ?>
        <?php if ($canPublish && $job['status'] === 'draft'): ?>
            <form method="post" action="/jobs/<?= e($job['id']) ?>/publish"><?= csrf_field() ?>
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Publish</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<?php if ($job['status'] === 'published'): ?>
    <div class="mb-4 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-700">
        Public link: <a class="font-mono underline" href="/jobs/public/<?= e($job['public_token']) ?>">/jobs/public/<?= e($job['public_token']) ?></a>
    </div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-2 text-sm font-semibold text-slate-900">Description</h2>
        <p class="whitespace-pre-line text-sm text-slate-600"><?= e($job['description'] ?: 'No description yet.') ?></p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Pipeline stages</h2>
        <?php if ($stages === []): ?>
            <p class="text-sm text-slate-400">Stages are created when you publish.</p>
        <?php else: ?>
            <ol class="space-y-1 text-sm text-slate-600">
                <?php foreach ($stages as $s): ?>
                    <li class="rounded-md bg-slate-50 px-3 py-1.5"><?= e($s['name']) ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</div>
