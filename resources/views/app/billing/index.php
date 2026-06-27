<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * Billing — the workspace's subscription, the public plan catalogue, and recent
 * invoices. Subscribing uses the in-app manual path (always works); a notice
 * explains when online payments are not configured. Subscribe buttons render only
 * for managers (canManage) and are disabled on the current plan.
 *
 * @var \App\Models\Subscription|null $subscription
 * @var int $currentPlanId
 * @var \App\Models\Plan[] $plans
 * @var array<int,array<string,mixed>> $invoices
 * @var bool $gatewayConfigured
 * @var bool $canManage
 */
$statusVariant = static fn (string $s): string => match ($s) {
    'active'   => 'green',
    'trialing' => 'info',
    'past_due' => 'amber',
    'paused'   => 'amber',
    'canceled', 'expired' => 'red',
    default    => 'slate',
};

$invoiceStatusVariant = static fn (string $s): string => match ($s) {
    'paid'    => 'green',
    'open', 'partial' => 'amber',
    'void', 'uncollectible' => 'red',
    'refunded' => 'slate',
    default    => 'slate',
};

// --- Current subscription card ------------------------------------------------
if ($subscription !== null) {
    $plan = $subscription->plan();
    $statusKey = $subscription->statusKey();
    $planName = $plan !== null ? (string) $plan->getAttribute('name') : 'Unknown plan';

    $lines = '<div class="space-y-3">';
    $lines .= '<div class="flex items-center justify-between gap-4">'
        . '<div><div class="text-base font-semibold text-slate-900 dark:text-white">' . e($planName) . '</div>'
        . ($plan !== null ? '<div class="text-sm text-slate-500">' . e($plan->formattedPrice()) . ' · ' . e($plan->intervalLabel()) . '</div>' : '')
        . '</div>'
        . component('badge', ['label' => $statusKey !== '' ? $statusKey : 'unknown', 'variant' => $statusVariant($statusKey), 'dot' => true])
        . '</div>';

    $startsAt = (string) ($subscription->getAttribute('starts_at') ?? '');
    $trialEnds = (string) ($subscription->getAttribute('trial_ends_at') ?? '');
    $endsAt = (string) ($subscription->getAttribute('ends_at') ?? '');
    $meta = [];
    if ($startsAt !== '') {
        $meta[] = '<span class="text-slate-500">Started</span> <span class="text-slate-700 dark:text-slate-200">' . e($startsAt) . '</span>';
    }
    if ($subscription->onTrial() && $trialEnds !== '') {
        $meta[] = '<span class="text-slate-500">Trial ends</span> <span class="text-slate-700 dark:text-slate-200">' . e($trialEnds) . '</span>';
    }
    if ($endsAt !== '') {
        $meta[] = '<span class="text-slate-500">Renews / ends</span> <span class="text-slate-700 dark:text-slate-200">' . e($endsAt) . '</span>';
    }
    if ($meta !== []) {
        $lines .= '<div class="flex flex-wrap gap-x-6 gap-y-1 text-sm">' . implode('', array_map(static fn ($m) => '<div>' . $m . '</div>', $meta)) . '</div>';
    }
    $lines .= '</div>';

    $currentCard = component('card', ['title' => 'Current subscription', 'slot' => $lines]);
} else {
    $currentCard = component('card', [
        'title' => 'Current subscription',
        'slot'  => component('state', [
            'variant' => 'empty',
            'title'   => 'No active subscription',
            'message' => 'Choose a plan below to start your workspace subscription.',
        ]),
    ]);
}

// --- Available plans grid -----------------------------------------------------
$planCards = '';
foreach ($plans as $plan) {
    $planId = (int) $plan->getKey();
    $isCurrent = $planId === $currentPlanId;

    $body = '<div class="space-y-4">';
    $body .= '<div><div class="text-2xl font-bold text-slate-900 dark:text-white">' . e($plan->formattedPrice()) . '</div>'
        . '<div class="text-xs uppercase tracking-wide text-slate-400">' . e($plan->intervalLabel()) . '</div></div>';

    $description = (string) ($plan->getAttribute('description') ?? '');
    if ($description !== '') {
        $body .= '<p class="text-sm text-slate-500 dark:text-slate-400">' . e($description) . '</p>';
    }

    $features = $plan->getAttribute('features');
    if (is_array($features) && $features !== []) {
        $items = '';
        foreach ($features as $key => $value) {
            $label = is_int($key) ? (string) $value : ($key . ': ' . (is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value));
            $items .= '<li class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">'
                . '<span class="mt-0.5 text-brand-500">&#10003;</span><span>' . e($label) . '</span></li>';
        }
        $body .= '<ul class="space-y-1">' . $items . '</ul>';
    }

    $trialDays = (int) $plan->getAttribute('trial_days');
    if ($trialDays > 0) {
        $body .= '<div class="text-xs text-slate-500">' . e($trialDays . '-day free trial') . '</div>';
    }

    // Subscribe / switch action — managers only; disabled on the current plan.
    if ($canManage) {
        if ($isCurrent) {
            $body .= '<div class="pt-1">'
                . component('button', ['label' => 'Current plan', 'variant' => 'secondary', 'block' => true, 'disabled' => true])
                . '</div>';
        } else {
            $body .= '<form method="POST" action="' . e(url('billing/subscribe')) . '" class="pt-1">'
                . csrf_field()
                . '<input type="hidden" name="plan_id" value="' . e((string) $planId) . '">'
                . component('button', ['label' => ($currentPlanId > 0 ? 'Switch to this plan' : 'Subscribe'), 'type' => 'submit', 'block' => true])
                . '</form>';
        }
    } elseif ($isCurrent) {
        $body .= '<div class="pt-1">' . component('badge', ['label' => 'Current plan', 'variant' => 'brand']) . '</div>';
    }

    $body .= '</div>';

    $planCards .= '<div>' . component('card', ['title' => (string) $plan->getAttribute('name'), 'slot' => $body]) . '</div>';
}

if ($planCards === '') {
    $plansSection = component('state', ['variant' => 'empty', 'title' => 'No plans available', 'message' => 'There are no plans to subscribe to right now.']);
} else {
    $plansSection = '<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">' . $planCards . '</div>';
}

// --- Invoices table -----------------------------------------------------------
$invoiceRows = [];
foreach ($invoices as $inv) {
    $statusKey = (string) ($inv['status_key'] ?? '');
    $statusLabel = (string) ($inv['status_label'] ?? $statusKey);
    $invoiceRows[] = [
        '<span class="font-medium text-slate-900 dark:text-white">' . e((string) ($inv['number'] ?? '')) . '</span>',
        component('badge', ['label' => $statusLabel !== '' ? $statusLabel : 'unknown', 'variant' => $invoiceStatusVariant($statusKey), 'dot' => true]),
        '<span class="text-sm text-slate-700 dark:text-slate-200">' . e(number_format((float) ($inv['total_amount'] ?? 0), 2)) . '</span>',
        '<span class="text-sm text-slate-500">' . e((string) ($inv['issued_at'] ?? '')) . '</span>',
    ];
}
?>
<div class="space-y-6">
    <?= component('page-header', [
        'title'    => 'Billing',
        'subtitle' => 'Manage your workspace subscription, plan and invoices.',
    ]) ?>

    <?php if (! $gatewayConfigured): ?>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-300">
            Online payments are not configured — subscriptions are managed manually.
        </div>
    <?php endif; ?>

    <?= $currentCard ?>

    <div class="space-y-3">
        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Available plans</h2>
        <?= $plansSection ?>
    </div>

    <?= component('card', [
        'title' => 'Recent invoices',
        'slot'  => component('table', [
            'columns' => [
                'Invoice',
                'Status',
                ['label' => 'Total', 'align' => 'start'],
                'Issued',
            ],
            'rows'  => $invoiceRows,
            'empty' => 'No invoices yet.',
        ]),
    ]) ?>
</div>
<?php $this->endSection(); ?>
