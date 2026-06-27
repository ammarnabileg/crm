<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/** @var array<int,array<string,mixed>> $jobs @var array<int,int> $counts */
$badge = static fn (string $s): string => match ($s) {
    'open'   => 'badge-green',
    'draft'  => 'badge-amber',
    'paused' => 'badge-amber',
    default  => 'badge-red',
};
$canManage = can('recruitment.manage');
$wsSlug = (string) (tenant()->workspace()?->getAttribute('slug') ?? '');
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">Jobs</h1>
        <?php if ($wsSlug !== ''): ?>
            <a href="<?= e(url('careers/' . $wsSlug)) ?>" target="_blank" rel="noopener"
               class="btn-secondary inline-flex items-center gap-1.5"
               title="Opens your company's public careers page, where anyone can see and apply to published jobs">
                View public careers page
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5h5m0 0v5m0-5L10 14M5 5h4M5 5v14h14v-4"/></svg>
            </a>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
        <form method="post" action="<?= e(url('jobs')) ?>" class="flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <?= csrf_field() ?>
            <div class="flex-1 min-w-[240px]">
                <label class="block text-sm font-medium text-slate-700" for="title">New job title</label>
                <input id="title" name="title" required maxlength="160" value="<?= e(old('title')) ?>"
                       class="mt-1 w-full rounded-lg border-slate-300" placeholder="e.g. Senior Backend Engineer">
            </div>
            <button type="submit" class="btn-primary">Create job</button>
        </form>
    <?php endif; ?>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Title</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Applications</th>
                    <th class="px-4 py-3 font-medium text-end">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if ($jobs === []): ?>
                    <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No jobs yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($jobs as $job): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <a class="font-medium text-indigo-600 hover:underline" href="<?= e(url('jobs/board?job=' . $job['id'])) ?>"><?= e((string) $job['title']) ?></a>
                        </td>
                        <td class="px-4 py-3"><span class="<?= e($badge((string) $job['status'])) ?>"><?= e((string) $job['status']) ?></span></td>
                        <td class="px-4 py-3 text-slate-700"><?= e((string) ($counts[(int) $job['id']] ?? 0)) ?></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <a class="btn-secondary" href="<?= e(url('jobs/board?job=' . $job['id'])) ?>">Board</a>
                                <?php if ($canManage): ?>
                                    <?php foreach (['publish' => 'Publish', 'close' => 'Close', 'archive' => 'Archive'] as $action => $label): ?>
                                        <form method="post" action="<?= e(url('jobs/' . $action)) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e((string) $job['id']) ?>">
                                            <button type="submit" class="btn-ghost"><?= e($label) ?></button>
                                        </form>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
