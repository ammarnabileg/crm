<?php
/** @var array<string,mixed> $execution */
/** @var list<array<string,mixed>> $steps */
$statusCls = static fn (string $s): string => match ($s) {
    'completed' => 'bg-emerald-50 text-emerald-700',
    'failed', 'error' => 'bg-rose-50 text-rose-700',
    'running' => 'bg-amber-50 text-amber-700',
    'skipped' => 'bg-slate-100 text-slate-500',
    default => 'bg-slate-100 text-slate-500',
};
?>
<div class="mb-6">
    <a href="/workflows" data-pjax class="text-xs font-medium text-slate-400 hover:text-slate-600">&larr; Workflows</a>
    <div class="mt-1 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold text-slate-900"><?= e($execution['workflow_name'] ?? 'Execution') ?></h1>
        <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $statusCls((string) $execution['status']) ?>"><?= e($execution['status']) ?></span>
    </div>
    <p class="mt-1 text-sm text-slate-500">
        on <span class="font-mono text-indigo-600"><?= e($execution['trigger_event']) ?></span>
        · <?= e($execution['steps_done']) ?>/<?= e($execution['steps_total']) ?> steps
        · started <?= e($execution['started_at'] ?? $execution['created_at']) ?> UTC
        <?php if (! empty($execution['finished_at'])): ?> · finished <?= e($execution['finished_at']) ?> UTC<?php endif; ?>
    </p>
    <?php if (! empty($execution['error'])): ?>
        <div class="mt-3 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($execution['error']) ?></div>
    <?php endif; ?>
</div>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Steps</h2>
    <?php if ($steps === []): ?>
        <p class="px-5 py-6 text-sm text-slate-400">No steps recorded.</p>
    <?php else: ?>
        <ol class="divide-y divide-slate-100">
            <?php foreach ($steps as $s): ?>
                <li class="flex items-start gap-3 px-5 py-3">
                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-500"><?= e((int) $s['step_index'] + 1) ?></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-sm text-slate-800"><?= e($s['action']) ?></span>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-medium <?= $statusCls((string) $s['status']) ?>"><?= e($s['status']) ?></span>
                        </div>
                        <?php if (! empty($s['output'])): ?>
                            <div class="mt-1 whitespace-pre-wrap break-words rounded-md bg-slate-50 px-3 py-2 font-mono text-xs text-slate-600"><?= e($s['output']) ?></div>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>
