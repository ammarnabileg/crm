<?php
/** @var array<string,mixed> $job */
/** @var list<array<string,mixed>> $stages */
/** @var array<string, list<array<string,mixed>>> $byStage */
/** @var bool $canManage */
?>
<div class="mb-6">
    <a href="/jobs/<?= e($job['id']) ?>" class="text-sm text-indigo-600 hover:underline">&larr; <?= e($job['title']) ?></a>
    <h1 class="mt-1 text-2xl font-semibold text-slate-900">Pipeline</h1>
</div>

<div class="flex gap-4 overflow-x-auto pb-4">
    <?php foreach ($stages as $stage): ?>
        <?php $apps = $byStage[(string) $stage['id']] ?? []; ?>
        <div class="w-72 shrink-0 rounded-2xl border border-slate-200 bg-slate-50/60 p-3">
            <div class="mb-3 flex items-center justify-between px-1">
                <span class="text-sm font-semibold text-slate-700"><?= e($stage['name']) ?></span>
                <span class="rounded-full bg-white px-2 py-0.5 text-xs text-slate-500"><?= count($apps) ?></span>
            </div>
            <div class="space-y-2">
                <?php foreach ($apps as $app): ?>
                    <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                        <a href="/candidates/<?= e($app['user_id'] ?? '') ?>" class="text-sm font-medium text-indigo-600 hover:underline"><?= e($app['name']) ?></a>
                        <div class="text-xs text-slate-500"><?= e($app['email']) ?></div>
                        <?php if ($canManage): ?>
                            <form method="post" action="/jobs/<?= e($job['id']) ?>/pipeline/move" class="mt-2 flex gap-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="application_id" value="<?= e($app['id']) ?>">
                                <select name="stage_id" class="grow rounded-md border border-slate-300 px-2 py-1 text-xs">
                                    <?php foreach ($stages as $s): ?>
                                        <option value="<?= e($s['id']) ?>" <?= (string) $s['id'] === (string) $stage['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="rounded-md bg-slate-800 px-2 py-1 text-xs font-medium text-white hover:bg-slate-700">Move</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($apps === []): ?><p class="px-1 text-xs text-slate-400">Empty</p><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
