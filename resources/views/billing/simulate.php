<?php
/** @var array<string,mixed>|null $payment */
$money = static fn (int $c): string => '$' . number_format($c / 100, 2);
?>
<div class="mx-auto max-w-md">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Confirm top-up (test mode)</h1>
        <p class="mt-1 text-sm text-slate-500">No live payment gateway is configured, so this simulates the Fawaterak checkout to credit your wallet.</p>
    </div>

    <?php if ($payment === null): ?>
        <div class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700">Top-up not found. <a href="/billing" class="underline">Back to billing</a>.</div>
    <?php elseif ((int) ($payment['credited'] ?? 0) === 1): ?>
        <div class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">This top-up was already credited. <a href="/billing" class="underline">Back to billing</a>.</div>
    <?php else: ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">Amount</div>
            <div class="mt-1 text-3xl font-bold text-slate-900"><?= e($money((int) $payment['amount_cents'])) ?></div>
            <form method="post" action="/billing/topup/simulate/<?= e($payment['id']) ?>" class="mt-5">
                <?= csrf_field() ?>
                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Pay &amp; credit wallet</button>
            </form>
            <a href="/billing" class="mt-3 block text-center text-xs text-slate-400 hover:text-slate-600">Cancel</a>
        </div>
    <?php endif; ?>
</div>
