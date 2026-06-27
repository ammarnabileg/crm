<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * @var \App\Models\Job $job
 * @var array<int,array<string,mixed>> $stages
 * @var array<int,array<int,array<string,mixed>>> $byStage
 */
$canManage = can('recruitment.manage');
?>
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Pipeline — <?= e((string) $job->title) ?></h1>
        <a href="<?= e(url('jobs')) ?>" class="btn-secondary">All jobs</a>
    </div>

    <div class="flex gap-4 overflow-x-auto pb-4">
        <?php foreach ($stages as $stage): ?>
            <?php $cards = $byStage[(int) $stage['id']] ?? []; ?>
            <div class="w-72 shrink-0 rounded-xl bg-slate-50 ring-1 ring-slate-200">
                <div class="flex items-center justify-between border-b border-slate-200 px-3 py-2">
                    <span class="flex items-center gap-2 font-medium text-slate-800">
                        <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: <?= e((string) ($stage['color'] ?? '#94a3b8')) ?>"></span>
                        <?= e((string) $stage['name']) ?>
                    </span>
                    <span class="rounded-full bg-slate-200 px-2 text-xs text-slate-600"><?= e((string) count($cards)) ?></span>
                </div>
                <div class="space-y-2 p-2">
                    <?php if ($cards === []): ?>
                        <p class="px-1 py-2 text-xs text-slate-400">No candidates</p>
                    <?php endif; ?>
                    <?php foreach ($cards as $app): ?>
                        <div class="rounded-lg bg-white p-3 shadow-sm ring-1 ring-slate-200">
                            <a class="block font-medium text-slate-800 hover:text-indigo-600" href="<?= e(url('applications/show?id=' . $app['id'])) ?>">
                                <?= e((string) ($app['candidate_name'] ?? 'Candidate')) ?>
                            </a>
                            <?php if (! empty($app['score'])): ?>
                                <span class="badge-green mt-1 inline-block">Score <?= e((string) $app['score']) ?></span>
                            <?php endif; ?>
                            <?php if ($canManage): ?>
                                <form method="post" action="<?= e(url('jobs/board/move')) ?>" class="mt-2 flex items-center gap-1">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="application_id" value="<?= e((string) $app['id']) ?>">
                                    <input type="hidden" name="job_id" value="<?= e((string) $job->id) ?>">
                                    <select name="stage_id" class="w-full rounded border-slate-300 text-xs">
                                        <?php foreach ($stages as $s): ?>
                                            <option value="<?= e((string) $s['id']) ?>" <?= (int) $s['id'] === (int) $stage['id'] ? 'selected' : '' ?>>
                                                <?= e((string) $s['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn-ghost text-xs">Move</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php $this->endSection(); ?>
