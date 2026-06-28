<?php
/** @var list<array<string,mixed>> $workflows */
/** @var list<array<string,mixed>> $executions */
/** @var bool $canCreate */
/** @var string|null $status */
?>
<div class="mb-6 flex items-start justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Workflows</h1>
        <p class="mt-1 text-sm text-slate-500">Automations that react to events in this workspace. The engine runs them centrally and records every execution &mdash; modules never automate themselves.</p>
    </div>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Active workflows</h2>
            <?php if ($workflows === []): ?>
                <p class="px-5 py-6 text-sm text-slate-400">No workflows yet. Create one to start automating.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($workflows as $w): ?>
                        <li class="flex items-center justify-between px-5 py-3 text-sm">
                            <div>
                                <div class="font-medium text-slate-800"><?= e($w['name']) ?></div>
                                <div class="text-xs text-slate-400">on <span class="font-mono text-indigo-600"><?= e($w['trigger_event']) ?></span></div>
                            </div>
                            <?php if ((int) $w['enabled'] === 1): ?>
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Enabled</span>
                            <?php else: ?>
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">Disabled</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Recent executions</h2>
            <?php if ($executions === []): ?>
                <p class="px-5 py-6 text-sm text-slate-400">No executions yet. They appear here when a trigger fires.</p>
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

    <?php if ($canCreate): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm self-start">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">New workflow</h2>
            <form method="post" action="/workflows" class="space-y-3">
                <?= csrf_field() ?>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Name</label>
                    <input name="name" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. AI screen new applicants">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Trigger</label>
                    <select name="trigger_event" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="application.submitted">When an application is submitted</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">AI capability</label>
                    <select name="capability" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="summarize_candidate">Summarize candidate</option>
                        <option value="candidate_recommendation">Candidate recommendation</option>
                    </select>
                </div>
                <p class="text-xs text-slate-400">Creates a 2-step flow: audit the event, then run the AI capability through the central AI Engine.</p>
                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create &amp; enable</button>
            </form>
        </div>
    <?php endif; ?>
</div>
