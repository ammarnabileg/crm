<?php
use HaHireAI\Modules\Recruitment\Domain\ApplicationStatus;

/** @var list<array<string,mixed>> $applications */
/** @var list<array<string,mixed>> $offers */
/** @var string $workspaceName */
/** @var string|null $status */

$badge = static function (string $s): string {
    return match ($s) {
        'hired', 'qualified', 'offer' => 'bg-emerald-100 text-emerald-700',
        'rejected', 'disqualified', 'withdrawn' => 'bg-rose-100 text-rose-700',
        default => 'bg-slate-100 text-slate-600',
    };
};
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">My applications</h1>
    <p class="mt-1 text-sm text-slate-500">Track your applications and offers at <span class="font-medium text-slate-700"><?= e($workspaceName) ?></span>.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<?php
$pendingOffers = array_values(array_filter($offers, static fn (array $o): bool => (string) $o['status'] === 'sent'));
if ($pendingOffers !== []): ?>
    <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-emerald-800">Offers awaiting your decision</h2>
        <div class="space-y-3">
            <?php foreach ($pendingOffers as $o): ?>
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-white px-4 py-3">
                    <div>
                        <div class="text-sm font-semibold text-slate-900"><?= e($o['title']) ?> <span class="font-normal text-slate-400">· <?= e($o['job_title']) ?></span></div>
                        <?php if ($o['salary'] !== null): ?><div class="text-xs text-slate-500"><?= e(number_format((float) $o['salary'])) ?> <?= e($o['currency']) ?></div><?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2">
                        <form method="post" action="/portal/offers/<?= e($o['id']) ?>/accept"><?= csrf_field() ?><button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700">Accept</button></form>
                        <form method="post" action="/portal/offers/<?= e($o['id']) ?>/decline"><?= csrf_field() ?><button class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50">Decline</button></form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <?php if ($applications === []): ?>
        <p class="px-5 py-8 text-center text-sm text-slate-400">No applications yet. <a href="/portal/jobs" class="text-indigo-600 hover:underline">Browse open jobs →</a></p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($applications as $a): ?>
                <li class="flex items-center justify-between px-5 py-4 text-sm">
                    <div>
                        <a href="/portal/applications/<?= e($a['id']) ?>" class="font-medium text-indigo-600 hover:underline"><?= e($a['job_title']) ?></a>
                        <div class="text-xs text-slate-400">Applied <?= e($a['applied_at']) ?><?php if (! empty($a['stage'])): ?> · <?= e($a['stage']) ?><?php endif; ?></div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $badge((string) $a['status']) ?>"><?= e(ApplicationStatus::label((string) $a['status'])) ?></span>
                        <a href="/portal/applications/<?= e($a['id']) ?>" class="text-xs font-medium text-slate-400 hover:text-slate-600">Details →</a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php
$otherOffers = array_values(array_filter($offers, static fn (array $o): bool => (string) $o['status'] !== 'sent'));
if ($otherOffers !== []): ?>
    <div class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-3"><h2 class="text-sm font-semibold text-slate-900">Offer history</h2></div>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($otherOffers as $o): ?>
                <li class="flex items-center justify-between px-5 py-3 text-sm">
                    <div>
                        <span class="font-medium text-slate-800"><?= e($o['title']) ?></span>
                        <span class="text-slate-400">· <?= e($o['job_title']) ?></span>
                        <?php if ((string) ($o['proposed_by'] ?? '') === 'candidate'): ?><span class="ml-1 rounded bg-indigo-50 px-1.5 py-0.5 text-xs font-medium text-indigo-600">your proposal</span><?php endif; ?>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600"><?= e($o['status']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
