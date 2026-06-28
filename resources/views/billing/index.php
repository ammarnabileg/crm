<?php
/** @var array<string,mixed>|null $subscription */
/** @var list<array<string,mixed>> $plans */
/** @var list<array<string,mixed>> $invoices */
/** @var bool $canManage */
/** @var string|null $status */
/** @var string|null $error */

$sub = $subscription;
$currentPlanId = $sub['plan']['id'] ?? null;
$subStatus = $sub['status'] ?? null;

$statusBadge = static function (?string $s): string {
    return match ($s) {
        'active' => 'bg-emerald-50 text-emerald-700',
        'trialing' => 'bg-indigo-50 text-indigo-700',
        'past_due' => 'bg-amber-50 text-amber-700',
        'suspended', 'canceled' => 'bg-rose-50 text-rose-700',
        default => 'bg-slate-100 text-slate-500',
    };
};
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Billing</h1>
    <p class="mt-1 text-sm text-slate-500">Your plan, subscription status, and invoices.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

<?php if ($subStatus === 'suspended'): ?>
    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">Your subscription is <strong>suspended</strong>. Choose a plan below to reactivate.</div>
<?php elseif ($subStatus === 'past_due'): ?>
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">Payment is <strong>past due</strong>. Update your plan to avoid suspension.</div>
<?php endif; ?>

<!-- Current subscription -->
<div class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <?php if ($sub === null): ?>
        <div class="text-sm text-slate-500">No active subscription. Choose a plan to get started.</div>
    <?php else: ?>
        <div class="flex items-center justify-between">
            <div>
                <div class="text-xs uppercase tracking-wide text-slate-400">Current plan</div>
                <div class="mt-1 text-xl font-bold text-slate-900"><?= e($sub['plan']['name'] ?? '—') ?></div>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-medium <?= $statusBadge($subStatus) ?>"><?= e($subStatus) ?></span>
        </div>
        <div class="mt-3 grid gap-2 text-sm text-slate-500 sm:grid-cols-2">
            <?php if (! empty($sub['trial_ends_at']) && $subStatus === 'trialing'): ?>
                <div>Trial ends: <span class="font-medium text-slate-700"><?= e($sub['trial_ends_at']) ?> UTC</span></div>
            <?php endif; ?>
            <?php if (! empty($sub['current_period_end'])): ?>
                <div>Renews: <span class="font-medium text-slate-700"><?= e($sub['current_period_end']) ?> UTC</span></div>
            <?php endif; ?>
            <?php if ((int) ($sub['cancel_at_period_end'] ?? 0) === 1): ?>
                <div class="text-amber-600">Cancels at period end.</div>
            <?php endif; ?>
        </div>
        <?php if ($canManage && in_array($subStatus, ['active', 'trialing'], true) && (int) ($sub['cancel_at_period_end'] ?? 0) === 0): ?>
            <form method="post" action="/billing/cancel" class="mt-4" onsubmit="return confirm('Cancel at the end of the current period?')">
                <?= csrf_field() ?>
                <button class="text-sm font-medium text-rose-600 hover:text-rose-700">Cancel subscription</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Plans -->
<div class="grid gap-4 lg:grid-cols-3">
    <?php foreach ($plans as $plan): ?>
        <?php $isCurrent = $currentPlanId !== null && (string) $plan['id'] === (string) $currentPlanId; ?>
        <div class="rounded-2xl border <?= $isCurrent ? 'border-indigo-300 ring-1 ring-indigo-200' : 'border-slate-200' ?> bg-white p-6 shadow-sm">
            <div class="flex items-baseline justify-between">
                <h3 class="text-lg font-semibold text-slate-900"><?= e($plan['name']) ?></h3>
                <?php if ($isCurrent): ?><span class="text-xs font-medium text-indigo-600">Current</span><?php endif; ?>
            </div>
            <div class="mt-1 text-2xl font-bold text-slate-900">
                <?php if ((int) $plan['price_cents'] === 0): ?>Free<?php else: ?>$<?= number_format($plan['price_cents'] / 100, 0) ?><span class="text-sm font-normal text-slate-400">/<?= e($plan['interval']) ?></span><?php endif; ?>
            </div>
            <p class="mt-1 text-sm text-slate-500"><?= e($plan['description'] ?? '') ?></p>

            <ul class="mt-4 space-y-1 text-sm text-slate-600">
                <?php foreach ((array) ($plan['features'] ?? []) as $f): ?>
                    <li>✓ <span class="capitalize"><?= e(str_replace('_', ' ', (string) $f)) ?></span></li>
                <?php endforeach; ?>
                <?php
                $limits = (array) ($plan['limits'] ?? []);
                $fmt = static fn ($v): string => (int) $v === -1 ? 'Unlimited' : (string) $v;
                ?>
                <li class="text-slate-400">Members: <?= e($fmt($limits['members'] ?? -1)) ?> · Jobs: <?= e($fmt($limits['jobs'] ?? -1)) ?></li>
            </ul>

            <?php if ($canManage && ! $isCurrent): ?>
                <form method="post" action="/billing/subscribe" class="mt-5">
                    <?= csrf_field() ?>
                    <input type="hidden" name="plan_id" value="<?= e($plan['id']) ?>">
                    <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        <?= $sub === null ? 'Choose plan' : 'Switch to ' . e($plan['name']) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<!-- Invoices -->
<div class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
    <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Invoices</h2>
    <?php if ($invoices === []): ?>
        <p class="px-5 py-6 text-sm text-slate-400">No invoices yet.</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($invoices as $inv): ?>
                <li class="flex items-center justify-between px-5 py-3 text-sm">
                    <div>
                        <span class="font-mono text-xs text-slate-600"><?= e($inv['number']) ?></span>
                        <div class="text-xs text-slate-400"><?= e($inv['created_at']) ?> UTC</div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="font-medium text-slate-700">$<?= number_format(((int) $inv['amount_cents']) / 100, 2) ?> <?= e($inv['currency']) ?></span>
                        <?php $icls = (string) $inv['status'] === 'paid' ? 'bg-emerald-50 text-emerald-700' : ((string) $inv['status'] === 'void' ? 'bg-slate-100 text-slate-500' : 'bg-amber-50 text-amber-700'); ?>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $icls ?>"><?= e($inv['status']) ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
