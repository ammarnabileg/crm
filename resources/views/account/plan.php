<?php
/** @var list<array<string,mixed>> $plans */
/** @var string|null $currentPlanId */
/** @var int $maxWorkspaces */
/** @var int $activeWorkspaces */
/** @var string|null $expiresAt */
/** @var bool $paymentsEnabled */
/** @var string|null $status */
/** @var string|null $error */
$freeMode = ! $paymentsEnabled;
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Your plan</h1>
    <p class="mt-1 text-sm text-slate-500">Your account plan sets how many workspaces you can run at once. Each plan unlocks more capacity.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Workspaces running</div><div class="mt-1 text-2xl font-bold text-slate-900"><?= e($activeWorkspaces) ?> / <?= e($maxWorkspaces) ?></div></div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Renews</div><div class="mt-1 text-sm font-semibold text-slate-700"><?= $expiresAt ? e(substr((string) $expiresAt, 0, 10)) : '—' ?></div></div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Payments</div><div class="mt-1 text-sm font-semibold <?= $freeMode ? 'text-emerald-600' : 'text-slate-700' ?>"><?= $freeMode ? 'Free for a limited time' : 'Active' ?></div></div>
</div>

<?php if ($freeMode): ?>
    <div class="mb-4 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800">🎉 <strong>All plans are free for a limited time.</strong> Pick any plan below to expand your workspace capacity at no cost.</div>
<?php endif; ?>

<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
    <?php
    // Free tier as the first, always-available option.
    $cards = array_merge([[
        'id' => null, 'name' => 'Free', 'price_cents' => 0, 'currency' => 'USD', 'interval' => 'month',
        'description' => 'A single workspace to get started.', 'features' => [], 'limits' => ['workspaces' => 1],
    ]], $plans);
    ?>
    <?php foreach ($cards as $plan): ?>
        <?php
        $pid = $plan['id'] ?? null;
        $isCurrent = (string) ($currentPlanId ?? '') === (string) ($pid ?? '');
        $limits = is_array($plan['limits'] ?? null) ? $plan['limits'] : [];
        $ws = (int) ($limits['workspaces'] ?? 1);
        $price = (int) ($plan['price_cents'] ?? 0);
        ?>
        <div class="flex flex-col rounded-2xl border <?= $isCurrent ? 'border-indigo-400 ring-1 ring-indigo-200' : 'border-slate-200' ?> bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-slate-900"><?= e($plan['name']) ?></h3>
                <?php if ($isCurrent): ?><span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">Current</span><?php endif; ?>
            </div>
            <div class="mt-1 text-2xl font-bold text-slate-900">
                <?php if ($freeMode): ?>Free <span class="text-sm font-normal text-emerald-600">for a limited time</span>
                <?php elseif ($price === 0): ?>Free<?php else: ?>$<?= number_format($price / 100, 0) ?><span class="text-sm font-normal text-slate-400">/<?= e($plan['interval'] ?? 'month') ?></span><?php endif; ?>
            </div>
            <?php if ($freeMode && $price > 0): ?>
                <div class="text-xs text-slate-400">Normally <span class="line-through">$<?= number_format($price / 100, 0) ?>/<?= e($plan['interval'] ?? 'month') ?></span></div>
            <?php endif; ?>
            <div class="mt-3 inline-flex items-center gap-1 rounded-full bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600 self-start"><?= $ws ?> workspace<?= $ws === 1 ? '' : 's' ?></div>
            <?php if (! empty($plan['description'])): ?><p class="mt-2 text-sm text-slate-500"><?= e($plan['description']) ?></p><?php endif; ?>
            <ul class="mt-3 flex-1 space-y-1 text-sm text-slate-600">
                <?php foreach ((array) ($plan['features'] ?? []) as $f): ?><li>✓ <span class="capitalize"><?= e(str_replace('_', ' ', (string) $f)) ?></span></li><?php endforeach; ?>
            </ul>
            <div class="mt-5">
                <?php if ($isCurrent): ?>
                    <button disabled class="w-full cursor-default rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-400">Your current plan</button>
                <?php elseif ($freeMode): ?>
                    <form method="post" action="/account/plan">
                        <?= csrf_field() ?>
                        <input type="hidden" name="plan_id" value="<?= e((string) ($pid ?? '')) ?>">
                        <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Switch to <?= e($plan['name']) ?> (free)</button>
                    </form>
                <?php else: ?>
                    <button disabled title="Contact support to change your account plan" class="w-full cursor-not-allowed rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-300">Contact support</button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
