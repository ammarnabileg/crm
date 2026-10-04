<?php
/** @var int $balanceCents */
/** @var array<string,mixed>|null $plan */
/** @var int $seatPriceCents */
/** @var int $billableSeats */
/** @var int $coveredSeats */
/** @var list<array<string,mixed>> $premiumFeatures */
/** @var list<string> $activeFeatures */
/** @var list<array<string,mixed>> $transactions */
/** @var list<array<string,mixed>> $invoices */
/** @var bool $canManage */
/** @var bool $gatewayEnabled */
/** @var string|null $status */
/** @var string|null $error */

$money = static fn (int $c): string => '$' . number_format($c / 100, 2);
$locked = $plan !== null && (string) $plan['status'] === 'locked';
$active = $plan !== null && (string) $plan['status'] === 'active';
$activeMap = array_flip($activeFeatures);
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Build Your Workspace</h1>
    <p class="mt-1 text-sm text-slate-500">Your workspace is your company. Pick your team seats and the services you need — candidates are always free.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>
<?php if ($locked): ?><div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">🔒 Your plan is <strong>locked</strong> — the last renewal could not be funded. Top up the wallet, then re-activate the plan below.</div><?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <!-- Wallet -->
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="text-xs uppercase tracking-wide text-slate-400">Wallet balance</div>
        <div class="mt-1 text-3xl font-bold text-slate-900"><?= e($money($balanceCents)) ?></div>
        <p class="mt-1 text-xs text-slate-400">Prepaid credits (USD).</p>
        <?php if ($canManage): ?>
            <form method="post" action="/billing/topup" class="mt-4 flex items-center gap-2">
                <?= csrf_field() ?>
                <span class="text-slate-400">$</span>
                <input type="number" name="amount" min="1" step="0.01" placeholder="50.00" required
                       class="w-28 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Top up</button>
            </form>
            <?php if (! $gatewayEnabled): ?>
                <p class="mt-2 text-xs text-amber-600">Test mode: no live payment gateway configured — top-ups are simulated.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Plan status -->
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
        <div class="flex items-center justify-between">
            <div class="text-xs uppercase tracking-wide text-slate-400">Current plan</div>
            <?php if ($plan !== null): ?>
                <span class="rounded-full px-3 py-1 text-xs font-medium <?= $active ? 'bg-emerald-50 text-emerald-700' : ($locked ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-500') ?>"><?= e($plan['status']) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($plan === null): ?>
            <p class="mt-3 text-sm text-slate-500">No plan yet. The Owner is free; build your workspace below to add staff seats and premium services.</p>
        <?php else: ?>
            <div class="mt-3 grid gap-2 text-sm text-slate-600 sm:grid-cols-2">
                <div>Monthly cost: <span class="font-semibold text-slate-900"><?= e($money((int) $plan['monthly_cost_cents'])) ?></span></div>
                <div>Seats paid: <span class="font-medium text-slate-800"><?= e((int) $plan['seats_paid']) ?></span> · Staff now: <?= e($billableSeats) ?></div>
                <?php if (! empty($plan['period_end'])): ?><div>Renews: <span class="font-medium text-slate-800"><?= e($plan['period_end']) ?> UTC</span></div><?php endif; ?>
                <div>Auto-renew:
                    <?php if ($canManage): ?>
                        <form method="post" action="/billing/auto-renew" class="inline">
                            <?= csrf_field() ?>
                            <?php if ((int) $plan['auto_renew'] === 1): ?>
                                <button class="font-medium text-emerald-700 hover:underline">On — turn off</button>
                            <?php else: ?>
                                <input type="hidden" name="auto_renew" value="1">
                                <button class="font-medium text-rose-600 hover:underline">Off — turn on</button>
                            <?php endif; ?>
                        </form>
                    <?php else: ?>
                        <span class="font-medium"><?= (int) $plan['auto_renew'] === 1 ? 'On' : 'Off' ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($billableSeats > $coveredSeats): ?>
                <div class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">You have <?= e($billableSeats) ?> staff but only <?= e($coveredSeats) ?> seat(s) funded. Add seats or re-build your workspace.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($canManage): ?>
<!-- Build Your Workspace -->
<div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-slate-900"><?= $plan === null ? 'Build your workspace' : 'Re-build / renew' ?></h2>
    <p class="mt-1 text-sm text-slate-500">Two steps: choose your team seats, then toggle the services you need. Cost is debited from your wallet; the term runs one month.</p>

    <form method="post" action="/billing/compose" class="mt-4">
        <?= csrf_field() ?>
        <div class="grid gap-8 lg:grid-cols-2">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-indigo-600">1 · Team Seats</div>
                <label class="mt-2 block text-sm font-medium text-slate-700">Billable seats</label>
                <input type="number" id="seats" name="seats" min="<?= e($billableSeats) ?>" value="<?= e(max($billableSeats, $plan['seats_paid'] ?? 0)) ?>"
                       class="mt-1 w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm" oninput="recalc()">
                <p class="mt-1 text-xs text-slate-400">Owner: <strong>1 — Free</strong>. Each additional seat: <strong><?= e($money($seatPriceCents)) ?>/month</strong>. Candidates are never counted. Minimum = current staff (<?= e($billableSeats) ?>).</p>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-indigo-600">2 · Additional Services</div>
                <div class="mt-2 space-y-2">
                    <?php foreach ($premiumFeatures as $f): ?>
                        <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <span>
                                <input type="checkbox" name="features[]" value="<?= e($f['key']) ?>" data-price="<?= e((int) $f['price_cents']) ?>"
                                       <?= isset($activeMap[(string) $f['key']]) ? 'checked' : '' ?> onchange="recalc()" class="mr-2 align-middle">
                                <span class="font-medium text-slate-800"><?= e($f['name']) ?></span>
                                <span class="text-xs text-slate-400"><?= e($f['description'] ?? '') ?></span>
                            </span>
                            <span class="text-slate-500"><?= e($money((int) $f['price_cents'])) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Pre-activation review (docs/WALLET_AND_BILLING.md) -->
        <div class="mt-5 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
            <strong>Before you activate:</strong> review whether you need any extra services now. Add-ons added later still <em>expire with this same plan</em> — you won't get a fresh month for them.
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-slate-500">Estimated monthly cost: <span id="estCost" class="text-lg font-bold text-slate-900">$0.00</span></div>
            <div class="flex items-center gap-2">
                <?php if ($active): ?>
                    <button type="submit" formaction="/billing/change-plan"
                            onclick="return confirm('Apply these changes now? Only the rest of this term is settled — an upgrade is charged pro-rata and a downgrade is credited back to your wallet. The new monthly price takes over at the next renewal.');"
                            class="rounded-lg border border-indigo-300 px-5 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">Apply changes (prorated)</button>
                <?php endif; ?>
                <button type="submit"
                        onclick="return confirm('<?= $plan === null ? 'Activate this plan now?' : 'Re-activate for a full month now?' ?> The cost is debited from your wallet and the term runs one month.');"
                        class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"><?= $plan === null ? 'Activate plan' : 'Apply & renew (full month)' ?></button>
            </div>
        </div>
    </form>
</div>

<!-- Add-ons (only services not already active) -->
<?php if ($active): ?>
    <?php $available = array_values(array_filter($premiumFeatures, static fn (array $f): bool => ! isset($activeMap[(string) $f['key']]))); ?>
    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Add-ons</h2>
        <p class="mt-1 text-sm text-slate-500">Enable an extra service now. It's charged immediately and <strong>expires with your current plan</strong> on <?= e($plan['period_end'] ?? '') ?> UTC.</p>
        <?php if ($available === []): ?>
            <p class="mt-3 text-sm text-slate-400">All premium services are already active in your plan.</p>
        <?php else: ?>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <?php foreach ($available as $f): ?>
                    <form method="post" action="/billing/addons" class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="feature" value="<?= e($f['key']) ?>">
                        <div>
                            <div class="text-sm font-medium text-slate-800"><?= e($f['name']) ?></div>
                            <div class="text-xs text-slate-400"><?= e($money((int) $f['price_cents'])) ?> · expires with plan</div>
                        </div>
                        <button class="rounded-lg border border-indigo-200 px-3 py-1.5 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Add</button>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="post" action="/billing/seats" class="mt-4 border-t border-slate-100 pt-4" onsubmit="return confirm('Add one seat for a full month at <?= e($money($seatPriceCents)) ?>?');">
            <?= csrf_field() ?>
            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">+ Add a seat (<?= e($money($seatPriceCents)) ?> / month)</button>
        </form>
    </div>
<?php endif; ?>
<?php endif; ?>

<!-- History -->
<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Wallet history</h2>
        <?php if ($transactions === []): ?>
            <p class="px-5 py-6 text-sm text-slate-400">No wallet activity yet.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($transactions as $t): ?>
                    <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                        <div>
                            <span class="text-slate-700"><?= e($t['description'] ?? $t['type']) ?></span>
                            <div class="text-xs text-slate-400"><?= e($t['created_at']) ?> UTC · <?= e($t['source']) ?></div>
                        </div>
                        <span class="font-medium <?= (int) $t['amount_cents'] >= 0 ? 'text-emerald-600' : 'text-slate-700' ?>">
                            <?= (int) $t['amount_cents'] >= 0 ? '+' : '−' ?><?= e($money(abs((int) $t['amount_cents']))) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Invoices</h2>
        <?php if ($invoices === []): ?>
            <p class="px-5 py-6 text-sm text-slate-400">No invoices yet.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($invoices as $inv): ?>
                    <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                        <div>
                            <span class="font-mono text-xs text-slate-600"><?= e($inv['number']) ?></span>
                            <div class="text-xs text-slate-400"><?= e($inv['created_at']) ?> UTC</div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="font-medium text-slate-700"><?= e($money((int) $inv['amount_cents'])) ?></span>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700"><?= e($inv['status']) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<script>
function recalc() {
    var seatPrice = <?= (int) $seatPriceCents ?>;
    var seats = parseInt(document.getElementById('seats').value || '0', 10);
    var total = seats * seatPrice;
    document.querySelectorAll('input[name="features[]"]:checked').forEach(function (el) {
        total += parseInt(el.getAttribute('data-price') || '0', 10);
    });
    document.getElementById('estCost').textContent = '$' + (total / 100).toFixed(2);
}
document.addEventListener('DOMContentLoaded', recalc);
</script>
