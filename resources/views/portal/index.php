<?php
use HaHireAI\Modules\Recruitment\Domain\ApplicationStatus;

/** @var array<string,mixed> $overview */
/** @var list<array<string,mixed>> $workspaces */
/** @var string $currentWorkspaceId */
/** @var string $workspaceName */
/** @var array<string,mixed>|null $user */
/** @var string|null $status */

$counts = $overview['counts'];
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Welcome, <?= e($user['name'] ?? 'there') ?></h1>
        <p class="mt-1 text-sm text-slate-500">Your candidate portal for <span class="font-medium text-slate-700"><?= e($workspaceName) ?></span>.</p>
    </div>
    <?php if (count($workspaces) > 1): ?>
        <a href="/workspaces/select" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Switch workspace</a>
    <?php endif; ?>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="mb-6 grid grid-cols-3 gap-4">
    <a href="/portal/applications" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-indigo-300">
        <div class="text-3xl font-semibold text-slate-900"><?= e($counts['applications']) ?></div>
        <div class="mt-1 text-sm text-slate-500">Applications</div>
    </a>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="text-3xl font-semibold text-slate-900"><?= e($counts['interviews']) ?></div>
        <div class="mt-1 text-sm text-slate-500">Interviews</div>
    </div>
    <a href="/portal/applications" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-emerald-300">
        <div class="text-3xl font-semibold <?= $counts['offers_pending'] > 0 ? 'text-emerald-600' : 'text-slate-900' ?>"><?= e($counts['offers_pending']) ?></div>
        <div class="mt-1 text-sm text-slate-500">Offers awaiting you</div>
    </a>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <!-- Applications -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-900">Your applications</h2>
                <a href="/portal/applications" class="text-xs font-medium text-indigo-600 hover:underline">View all</a>
            </div>
            <?php if ($overview['applications'] === []): ?>
                <p class="px-5 py-6 text-sm text-slate-400">No applications yet. <a href="/portal/jobs" class="text-indigo-600 hover:underline">Browse open jobs →</a></p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach (array_slice($overview['applications'], 0, 5) as $a): ?>
                        <li class="flex items-center justify-between px-5 py-3 text-sm">
                            <a href="/portal/applications/<?= e($a['id']) ?>" class="font-medium text-indigo-600 hover:underline"><?= e($a['job_title']) ?></a>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600"><?= e(ApplicationStatus::label((string) $a['status'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Interviews -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-3"><h2 class="text-sm font-semibold text-slate-900">Interviews</h2></div>
            <?php if ($overview['interviews'] === []): ?>
                <p class="px-5 py-6 text-sm text-slate-400">No interviews scheduled yet.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($overview['interviews'] as $iv): ?>
                        <li class="flex items-center justify-between px-5 py-3 text-sm">
                            <div>
                                <span class="font-medium text-slate-800"><?= e($iv['job_title']) ?></span>
                                <div class="text-xs text-slate-400">
                                    <span class="uppercase"><?= e($iv['type']) ?></span> · <?= e($iv['status']) ?><?php if (! empty($iv['scheduled_at'])): ?> · <?= e($iv['scheduled_at']) ?> UTC<?php endif; ?>
                                </div>
                            </div>
                            <?php if (! empty($iv['meeting_link']) && $iv['status'] !== 'completed'): ?>
                                <a href="<?= e($iv['meeting_link']) ?>" target="_blank" rel="noopener" class="text-xs font-medium text-indigo-600 hover:underline">Join →</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-6">
        <!-- Latest jobs -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-900">Latest jobs</h2>
                <a href="/portal/jobs" class="text-xs font-medium text-indigo-600 hover:underline">All jobs</a>
            </div>
            <?php if ($overview['latest_jobs'] === []): ?>
                <p class="px-5 py-6 text-sm text-slate-400">No open jobs right now.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($overview['latest_jobs'] as $j): ?>
                        <li class="px-5 py-3 text-sm">
                            <div class="font-medium text-slate-800"><?= e($j['title']) ?></div>
                            <?php if (! empty($j['location'])): ?><div class="text-xs text-slate-400"><?= e($j['location']) ?></div><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Quick links -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Quick links</h2>
            <div class="space-y-2 text-sm">
                <a href="/portal/jobs" class="block text-indigo-600 hover:underline">Browse available jobs</a>
                <a href="/portal/applications" class="block text-indigo-600 hover:underline">Track applications &amp; offers</a>
                <a href="/portal/profile" class="block text-indigo-600 hover:underline">Edit my profile</a>
                <a href="/workspaces/select" class="block text-slate-500 hover:underline">Switch workspace</a>
            </div>
        </div>
    </div>
</div>
