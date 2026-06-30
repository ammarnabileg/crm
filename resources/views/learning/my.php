<?php
/**
 * @var list<array<string,mixed>> $enrollments
 * @var list<array<string,mixed>> $todos
 * @var list<array<string,mixed>> $certificates
 * @var string|null $status
 */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">My Learning</h1>
    <p class="mt-1 text-sm text-slate-500">Programs assigned to you and your progress.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        <?php if ($enrollments === []): ?>
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center text-sm text-slate-400 shadow-sm">
                You're not enrolled in any programs yet.
            </div>
        <?php else: ?>
            <?php foreach ($enrollments as $e): ?>
                <?php $pct = (int) $e['progress_percent']; $done = (string) $e['status'] === 'completed'; ?>
                <a href="/my-learning/<?= e($e['program_id']) ?>" class="block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-slate-900"><?= e($e['title']) ?></h3>
                            <?php if (! empty($e['summary'])): ?><p class="mt-1 line-clamp-1 text-sm text-slate-500"><?= e($e['summary']) ?></p><?php endif; ?>
                            <div class="mt-1 text-xs text-slate-400">
                                <?= e($e['category'] ?? 'General') ?>
                                <?php if (! empty($e['due_date'])): ?> · due <?= e(substr((string) $e['due_date'], 0, 10)) ?><?php endif; ?>
                            </div>
                        </div>
                        <?php if ($done): ?><span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Completed</span><?php endif; ?>
                    </div>
                    <div class="mt-3 flex items-center gap-3">
                        <div class="h-2 grow rounded-full bg-slate-100"><div class="h-2 rounded-full <?= $done ? 'bg-emerald-500' : 'bg-indigo-500' ?>" style="width:<?= max(2, $pct) ?>%"></div></div>
                        <span class="text-xs font-medium text-slate-600"><?= $pct ?>%</span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="space-y-4">
    <?php if (! empty($certificates)): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">My certificates</h2>
            <div class="space-y-2">
                <?php foreach ($certificates as $c): ?>
                    <a href="/my-learning/certificate/<?= e($c['serial']) ?>" class="flex items-center justify-between rounded-xl border border-slate-100 px-3 py-2 hover:bg-slate-50">
                        <span class="text-sm text-slate-700">🎓 <?= e($c['program_title'] ?? $c['title']) ?></span>
                        <span class="text-xs text-slate-400"><?= (int) $c['percent'] ?>%</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="self-start rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-900">My open to-dos</h2>
        <?php if ($todos === []): ?>
            <p class="text-xs text-slate-400">Nothing assigned to you.</p>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($todos as $t): ?>
                    <a href="/my-learning/<?= e($t['program_id']) ?>" class="block rounded-xl border border-slate-100 px-3 py-2 hover:bg-slate-50">
                        <div class="text-sm text-slate-700"><?= e($t['title']) ?></div>
                        <div class="text-xs text-slate-400">
                            <?= e($t['program_title'] ?? '') ?>
                            <?php if ($t['completion_mode'] === 'manager'): ?> · <span class="text-amber-600">manager-completed</span><?php endif; ?>
                            <?php if (! empty($t['due_date'])): ?> · due <?= e(substr((string) $t['due_date'], 0, 10)) ?><?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    </div>
</div>
