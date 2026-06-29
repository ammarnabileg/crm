<?php
/** @var list<array<string,mixed>> $workflows */
/** @var list<array<string,mixed>> $executions */
/** @var bool $canCreate */
/** @var string|null $status */
?>
<div class="mb-6 flex items-start justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Workflows</h1>
        <p class="mt-1 text-sm text-slate-500">Automations that react to events in this workspace. Build them visually &mdash; the engine runs them centrally and records every execution.</p>
    </div>
    <?php if ($canCreate): ?>
        <div class="flex items-center gap-2">
            <a href="/workflows/templates" data-pjax class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 8.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25a2.25 2.25 0 01-2.25 2.25h-2.25A2.25 2.25 0 0113.5 8.25V6z"/></svg>
                Templates
            </a>
            <a href="/workflows/new" data-pjax class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                New workflow
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Active workflows</h2>
            <?php if ($workflows === []): ?>
                <div class="px-5 py-10 text-center">
                    <p class="text-sm text-slate-400">No workflows yet.</p>
                    <?php if ($canCreate): ?><a href="/workflows/new" data-pjax class="mt-3 inline-block text-sm font-medium text-indigo-600 hover:underline">Build your first automation &rarr;</a><?php endif; ?>
                </div>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($workflows as $w): ?>
                        <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                            <a href="/workflows/<?= e($w['id']) ?>/edit" data-pjax class="min-w-0 flex-1">
                                <div class="truncate font-medium text-slate-800 hover:text-indigo-600"><?= e($w['name']) ?></div>
                                <div class="truncate text-xs text-slate-400">on <span class="font-mono text-indigo-600"><?= e($w['trigger_event']) ?></span></div>
                            </a>
                            <div class="flex shrink-0 items-center gap-2">
                                <?php if ((int) $w['enabled'] === 1): ?>
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Enabled</span>
                                <?php else: ?>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">Disabled</span>
                                <?php endif; ?>
                                <?php if ($canCreate): ?>
                                    <form method="post" action="/workflows/<?= e($w['id']) ?>/run" class="m-0">
                                        <?= csrf_field() ?>
                                        <button class="rounded-md border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50" title="Run once now">Run</button>
                                    </form>
                                    <form method="post" action="/workflows/<?= e($w['id']) ?>/toggle" class="m-0">
                                        <?= csrf_field() ?>
                                        <button class="rounded-md border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50"><?= (int) $w['enabled'] === 1 ? 'Pause' : 'Enable' ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Recent executions</h2>
            <?php if ($executions === []): ?>
                <p class="px-5 py-6 text-sm text-slate-400">No executions yet. They appear here when a trigger fires or you run a workflow.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($executions as $x): ?>
                        <li class="flex items-center justify-between px-5 py-3 text-sm">
                            <div>
                                <span class="font-medium text-slate-700"><?= e($x['workflow_name'] ?? 'workflow') ?></span>
                                <span class="text-slate-400">· <?= e($x['steps_done']) ?>/<?= e($x['steps_total']) ?> steps</span>
                                <div class="text-xs text-slate-400">on <span class="font-mono"><?= e($x['trigger_event']) ?></span></div>
                            </div>
                            <div class="text-right">
                                <?php
                                $cls = match ((string) $x['status']) {
                                    'completed' => 'bg-emerald-50 text-emerald-700',
                                    'failed' => 'bg-rose-50 text-rose-700',
                                    'running' => 'bg-amber-50 text-amber-700',
                                    default => 'bg-slate-100 text-slate-500',
                                };
                                ?>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $cls ?>"><?= e($x['status']) ?></span>
                                <div class="mt-0.5 text-xs text-slate-400"><?= e($x['created_at']) ?> UTC</div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <aside class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm self-start">
        <h2 class="mb-2 text-sm font-semibold text-slate-900">No-code automation</h2>
        <p class="text-sm text-slate-500">Drag triggers, conditions and actions onto a canvas and connect them. No code, no JSON &mdash; just pick from menus.</p>
        <ul class="mt-4 space-y-2 text-sm text-slate-600">
            <li class="flex gap-2"><span class="text-indigo-500">•</span> Triggers fire from events that already happen in your workspace.</li>
            <li class="flex gap-2"><span class="text-indigo-500">•</span> Actions reuse your existing services &mdash; AI, tasks, notifications, pipeline.</li>
            <li class="flex gap-2"><span class="text-indigo-500">•</span> Every run is logged with per-step results.</li>
        </ul>
        <?php if ($canCreate): ?>
            <a href="/workflows/new" data-pjax class="mt-5 block rounded-lg bg-indigo-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-indigo-700">Open the builder</a>
        <?php endif; ?>
    </aside>
</div>
