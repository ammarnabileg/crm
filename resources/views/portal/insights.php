<?php
/** @var list<array<string,mixed>> $reports */
/** @var string $workspaceName */
/** @var string|null $status */
$badge = static function (int $v): string {
    $c = $v >= 80 ? 'emerald' : ($v >= 65 ? 'indigo' : ($v >= 45 ? 'amber' : 'rose'));

    return 'bg-' . $c . '-50 text-' . $c . '-700';
};
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">My insights</h1>
    <p class="mt-1 text-sm text-slate-500">Your automated first-impression analysis for each role you applied to.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="mb-5 rounded-lg bg-slate-50 px-4 py-3 text-xs text-slate-500">
    These reports are <strong>read-only</strong> and computed with <strong>no AI</strong>. A new one is generated, and your
    profile insights refreshed, <strong>every time you apply to a new job</strong> — each score reflects your fit for that
    specific role.
</div>

<?php if ($reports === []): ?>
    <div class="rounded-2xl border border-slate-200 bg-white px-5 py-10 text-center text-sm text-slate-400 shadow-sm">
        No insights yet. Apply to a role that uses the First Impression review and your report will appear here.
    </div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($reports as $r): ?>
            <a href="/my-insights/<?= e((string) $r['id']) ?>" class="block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-indigo-300">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-semibold text-slate-900"><?= e((string) ($r['job_title'] ?? 'Role')) ?></h2>
                        <p class="mt-0.5 text-xs text-slate-400">
                            <?= e((string) ($r['workspace_name'] ?? '')) ?> · <?= e(date('M j, Y', strtotime((string) $r['created_at']))) ?>
                            · CV fit <?= (int) ($r['resume_score'] ?? 0) ?>%
                            <?php if (($r['social_score'] ?? null) !== null): ?> · social <?= (int) $r['social_score'] ?><?php endif; ?>
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <?php if ((int) ($r['passed'] ?? 0) === 1): ?>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Passed</span>
                        <?php else: ?>
                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">Filtered</span>
                        <?php endif; ?>
                        <span class="flex h-12 w-12 items-center justify-center rounded-full text-sm font-bold <?= $badge((int) ($r['overall_score'] ?? 0)) ?>"><?= (int) ($r['overall_score'] ?? 0) ?></span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
