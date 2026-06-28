<?php
/** @var array<string,mixed> $analytics */
$t = $analytics['totals'];
$money = static fn (int $c): string => '$' . number_format($c / 100, 2);
$card = static function (string $label, string $value): string {
    return '<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">'
        . '<div class="text-xs uppercase tracking-wide text-slate-400">' . e($label) . '</div>'
        . '<div class="mt-1 text-2xl font-bold text-slate-900">' . e($value) . '</div></div>';
};
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">AI Analytics</h1>
        <p class="mt-1 text-sm text-slate-500">AI performance and token consumption for this workspace.</p>
    </div>
    <a href="/ai" class="text-sm text-indigo-600 hover:underline">AI settings →</a>
</div>

<div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
    <?= $card('AI runs', (string) $t['runs']) ?>
    <?= $card('Tokens', number_format($t['tokens'])) ?>
    <?= $card('Cost', $money((int) $t['cost_cents'])) ?>
    <?= $card('Avg latency', $t['avg_latency_ms'] . ' ms') ?>
    <?= $card('Failed', (string) $t['failed']) ?>
    <?= $card('Fallbacks', (string) $t['fallbacks']) ?>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">By capability</h2>
        <?php if (($analytics['by_capability'] ?? []) === []): ?>
            <p class="px-5 py-6 text-sm text-slate-400">No AI runs yet.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($analytics['by_capability'] as $row): ?>
                    <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                        <span class="font-mono text-xs text-indigo-600"><?= e($row['capability']) ?></span>
                        <span class="text-slate-600"><?= e($row['runs']) ?> runs · <?= e(number_format((int) $row['tokens'])) ?> tok · <?= $money((int) $row['cost_cents']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">By provider</h2>
        <?php if (($analytics['by_provider'] ?? []) === []): ?>
            <p class="px-5 py-6 text-sm text-slate-400">No AI runs yet.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($analytics['by_provider'] as $row): ?>
                    <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                        <span class="font-medium text-slate-700"><?= e($row['provider']) ?></span>
                        <span class="text-slate-600"><?= e($row['runs']) ?> runs · <?= e(number_format((int) $row['tokens'])) ?> tokens</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
    <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Recent AI runs</h2>
    <?php if (($analytics['recent'] ?? []) === []): ?>
        <p class="px-5 py-6 text-sm text-slate-400">No AI runs yet.</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($analytics['recent'] as $s): ?>
                <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                    <div>
                        <span class="font-mono text-xs text-indigo-600"><?= e($s['capability']) ?></span>
                        <span class="text-slate-400">· <?= e($s['provider']) ?> · <?= e($s['status']) ?></span>
                    </div>
                    <div class="text-right text-xs text-slate-500"><?= e(number_format((int) $s['tokens'])) ?> tok · <?= e($s['latency_ms']) ?> ms · <?= e($s['created_at']) ?> UTC</div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
