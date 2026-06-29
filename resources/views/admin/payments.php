<?php
/** @var list<array<string,mixed>> $attempts */
/** @var array{total:int,success:int,failed:int,charged_cents:int} $stats */
/** @var string $filter */
$money = static fn (int $cents, string $cur = 'USD'): string => $cur . ' ' . number_format($cents / 100, 2);
$tab = static fn (string $key, string $label, string $cur): string =>
    '<a href="/payments' . ($key === '' ? '' : '?status=' . $key) . '" class="rounded-lg px-3 py-1.5 text-sm font-medium '
    . ($cur === $key ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100') . '">' . $label . '</a>';
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Payment charges</h1>
    <p class="mt-1 text-sm text-slate-500">Every charge against a member account — successes and failures. Failed charges include the exact cause and how to fix it.</p>
</div>

<div class="mb-6 grid gap-4 sm:grid-cols-4">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Attempts</div><div class="mt-1 text-2xl font-bold text-slate-900"><?= e($stats['total']) ?></div></div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Succeeded</div><div class="mt-1 text-2xl font-bold text-emerald-600"><?= e($stats['success']) ?></div></div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Failed</div><div class="mt-1 text-2xl font-bold text-rose-600"><?= e($stats['failed']) ?></div></div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Total charged</div><div class="mt-1 text-2xl font-bold text-slate-900"><?= e($money($stats['charged_cents'])) ?></div></div>
</div>

<div class="mb-4 flex items-center gap-2">
    <?= $tab('', 'All', $filter) ?>
    <?= $tab('success', 'Succeeded', $filter) ?>
    <?= $tab('failed', 'Failed', $filter) ?>
</div>

<div class="space-y-3">
    <?php foreach ($attempts as $a): ?>
        <?php $failed = (string) $a['status'] === 'failed'; ?>
        <div class="rounded-2xl border <?= $failed ? 'border-rose-200' : 'border-slate-200' ?> bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="min-w-0">
                    <div class="font-medium text-slate-800">
                        <?= e($a['workspace'] ?? '—') ?>
                        <span class="text-xs font-normal text-slate-400">· <?= e($a['plan'] ?? '—') ?> · <?= e($money((int) $a['amount_cents'], (string) $a['currency'])) ?></span>
                    </div>
                    <div class="text-xs text-slate-400">
                        <?= e($a['created_at']) ?> UTC · via <?= e($a['provider']) ?>
                        <?php if (! empty($a['reference'])): ?> · ref <span class="font-mono"><?= e($a['reference']) ?></span><?php endif; ?>
                    </div>
                </div>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $failed ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' ?>"><?= $failed ? 'Failed' : 'Succeeded' ?></span>
            </div>

            <?php if ($failed && isset($a['diagnosis'])): ?>
                <div class="mt-3 rounded-lg bg-rose-50/60 p-3 text-sm">
                    <div class="font-mono text-xs text-rose-700"><?= e($a['diagnosis']['code']) ?><?php if (! empty($a['error_message'])): ?> — <?= e($a['error_message']) ?><?php endif; ?></div>
                    <div class="mt-1 text-slate-700"><span class="font-medium text-slate-900">Cause:</span> <?= e($a['diagnosis']['cause']) ?></div>
                    <div class="mt-0.5 text-slate-700"><span class="font-medium text-slate-900">How to fix:</span> <?= e($a['diagnosis']['remedy']) ?></div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if ($attempts === []): ?>
        <div class="rounded-2xl border border-slate-200 bg-white px-5 py-10 text-center text-sm text-slate-400 shadow-sm">
            No charge attempts<?= $filter !== '' ? ' with status “' . e($filter) . '”' : '' ?> yet. Charges appear here once a real payment gateway is connected and payments are enabled.
        </div>
    <?php endif; ?>
</div>
