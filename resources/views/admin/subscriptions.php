<?php /** @var list<array<string,mixed>> $subscriptions */ ?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Subscriptions</h1>
    <p class="mt-1 text-sm text-slate-500">Every workspace subscription across the platform.</p>
</div>
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
            <tr><th class="px-5 py-3">Workspace</th><th class="px-5 py-3">Plan</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Renews / trial ends</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($subscriptions as $s): ?>
                <?php $cls = match ((string) $s['status']) { 'active' => 'bg-emerald-50 text-emerald-700', 'trialing' => 'bg-indigo-50 text-indigo-700', 'past_due' => 'bg-amber-50 text-amber-700', default => 'bg-rose-50 text-rose-700' }; ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-medium text-slate-800"><?= e($s['workspace']) ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($s['plan']) ?> <span class="text-xs text-slate-400">$<?= number_format(((int) $s['price_cents']) / 100, 0) ?></span></td>
                    <td class="px-5 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $cls ?>"><?= e($s['status']) ?></span></td>
                    <td class="px-5 py-3 text-xs text-slate-400"><?= e($s['current_period_end'] ?? $s['trial_ends_at'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($subscriptions === []): ?><tr><td colspan="4" class="px-5 py-8 text-center text-sm text-slate-400">No subscriptions yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
