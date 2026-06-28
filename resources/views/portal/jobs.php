<?php
/** @var list<array<string,mixed>> $jobs */
/** @var string $workspaceName */
/** @var string|null $status */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Available jobs</h1>
    <p class="mt-1 text-sm text-slate-500">Open roles at <span class="font-medium text-slate-700"><?= e($workspaceName) ?></span>.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<?php if ($jobs === []): ?>
    <div class="rounded-2xl border border-slate-200 bg-white px-5 py-10 text-center text-sm text-slate-400 shadow-sm">No open jobs right now. Check back soon.</div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($jobs as $j): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-slate-900"><?= e($j['title']) ?></h2>
                        <div class="mt-1 text-xs text-slate-400">
                            <?php if (! empty($j['location'])): ?><?= e($j['location']) ?><?php endif; ?>
                            <?php if (! empty($j['employment_type'])): ?> · <?= e($j['employment_type']) ?><?php endif; ?>
                            <?php if (! empty($j['seniority'])): ?> · <?= e($j['seniority']) ?><?php endif; ?>
                        </div>
                        <?php if (! empty($j['description'])): ?>
                            <p class="mt-2 line-clamp-3 text-sm text-slate-600"><?= e(mb_substr((string) $j['description'], 0, 240)) ?><?= mb_strlen((string) $j['description']) > 240 ? '…' : '' ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="shrink-0 text-right">
                        <?php if ((int) ($j['has_applied'] ?? 0) > 0): ?>
                            <span class="inline-block rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-500">Applied ✓</span>
                        <?php else: ?>
                            <form method="post" action="/portal/jobs/<?= e($j['id']) ?>/apply">
                                <?= csrf_field() ?>
                                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Apply</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
