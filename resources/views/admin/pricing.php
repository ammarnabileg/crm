<?php
/** @var int $seatPriceCents */
/** @var list<array<string,mixed>> $features */
/** @var string|null $status */
/** @var string|null $error */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Pricing</h1>
    <p class="mt-1 text-sm text-slate-500">The seat price and per-feature prices every workspace pays when composing its monthly plan. Basics (Jobs, Interviews) are free.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

<form method="post" action="/pricing" class="max-w-2xl space-y-6">
    <?= csrf_field() ?>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <label class="block text-sm font-medium text-slate-700">Per-seat price (USD / month)</label>
        <p class="text-xs text-slate-400">Charged for every staff member beyond the free Owner.</p>
        <div class="mt-2 flex items-center gap-2">
            <span class="text-slate-400">$</span>
            <input type="number" step="0.01" min="0" name="seat_price" value="<?= e(number_format($seatPriceCents / 100, 2, '.', '')) ?>"
                   class="w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h2 class="border-b border-slate-100 px-6 py-3 text-sm font-semibold text-slate-900">Features</h2>
        <div class="divide-y divide-slate-100">
            <?php foreach ($features as $f): ?>
                <div class="flex items-center justify-between px-6 py-3">
                    <div>
                        <div class="text-sm font-medium text-slate-800"><?= e($f['name']) ?>
                            <span class="ml-2 rounded-full px-2 py-0.5 text-xs <?= (string) $f['category'] === 'basic' ? 'bg-slate-100 text-slate-500' : 'bg-indigo-50 text-indigo-700' ?>"><?= e($f['category']) ?></span>
                        </div>
                        <div class="text-xs text-slate-400"><?= e($f['description'] ?? '') ?> · <code class="text-slate-400"><?= e($f['key']) ?></code></div>
                    </div>
                    <?php if ((string) $f['category'] === 'basic'): ?>
                        <span class="text-sm font-medium text-emerald-600">Free</span>
                    <?php else: ?>
                        <div class="flex items-center gap-1">
                            <span class="text-slate-400">$</span>
                            <input type="number" step="0.01" min="0" name="feature_price[<?= e($f['key']) ?>]" value="<?= e(number_format(((int) $f['price_cents']) / 100, 2, '.', '')) ?>"
                                   class="w-28 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <button class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Save pricing</button>
</form>
