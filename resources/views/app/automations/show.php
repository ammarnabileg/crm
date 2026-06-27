<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/** @var array<string,mixed> $automation */
/** @var bool $canManage */
$statusBadge = static fn (string $s): string => match ($s) {
    'success', 'completed' => 'badge badge-success',
    'failed', 'error'      => 'badge badge-danger',
    default                => 'badge',
};
?>
<div class="space-y-6">
    <a href="<?= e(url('automations')) ?>" class="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">
        <svg class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        All automations
    </a>

    <?php $this->include('partials.alerts'); ?>

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900 dark:text-white"><?= e($automation['name']) ?></h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Trigger: <span class="font-mono text-xs"><?= e($automation['trigger_event']) ?></span>
                · <span class="<?= $automation['is_active'] ? 'badge badge-success' : 'badge' ?>"><?= $automation['is_active'] ? 'Active' : 'Paused' ?></span>
                · version <?= e((string) $automation['version']) ?>
            </p>
        </div>
        <?php if ($canManage): ?>
            <div class="flex flex-wrap items-center gap-2">
                <form method="POST" action="<?= e(url('automations/toggle')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e((string) $automation['id']) ?>">
                    <button class="btn-secondary"><?= $automation['is_active'] ? 'Pause' : 'Activate' ?></button>
                </form>
                <form method="POST" action="<?= e(url('automations/publish')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e((string) $automation['id']) ?>">
                    <button class="btn-secondary">Publish version</button>
                </form>
                <form method="POST" action="<?= e(url('automations/delete')) ?>" data-confirm="Delete this automation? This cannot be undone.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e((string) $automation['id']) ?>">
                    <button class="btn-danger">Delete</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Steps</h2>
        <?php if ($automation['steps'] === []): ?>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">This automation has no steps.</p>
        <?php else: ?>
            <ol class="mt-3 space-y-2">
                <?php foreach ($automation['steps'] as $n => $step): ?>
                    <li class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"><?= (int) $n + 1 ?></span>
                        <span class="badge <?= $step['type'] === 'action' ? 'badge-success' : '' ?>"><?= e($step['type']) ?></span>
                        <span class="font-mono text-sm text-slate-800 dark:text-slate-200"><?= e($step['key']) ?></span>
                        <?php if ($step['config'] !== ''): ?>
                            <code class="ms-auto truncate rounded bg-slate-50 px-2 py-1 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400"><?= e($step['config']) ?></code>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Recent runs</h2>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">The execution log — every time this automation fired.</p>
        <?php if ($automation['runs'] === []): ?>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">No runs yet. This automation runs when its trigger fires.</p>
        <?php else: ?>
            <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($automation['runs'] as $run): ?>
                    <li class="flex items-center justify-between gap-3 py-2 text-sm">
                        <span class="<?= e($statusBadge((string) $run['status'])) ?>"><?= e((string) $run['status']) ?></span>
                        <span class="font-mono text-xs text-slate-500 dark:text-slate-400"><?= e((string) $run['trigger_event']) ?></span>
                        <span class="text-xs text-slate-400"><?= e((string) $run['created_at']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php $this->endSection(); ?>
