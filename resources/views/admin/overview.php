<?php
/** @var array<string,mixed> $metrics */
/** @var string $health */
/** @var list<array<string,mixed>> $alerts */
/** @var string|null $status */

$m = $metrics;
$money = static fn (int $cents): string => '$' . number_format($cents / 100, 2);
$healthCls = $health === 'healthy' ? 'bg-emerald-50 text-emerald-700' : ($health === 'degraded' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700');

$card = static function (string $label, string $value, string $sub = ''): string {
    $sub = $sub !== '' ? '<div class="mt-0.5 text-xs text-slate-400">' . e($sub) . '</div>' : '';

    return '<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">'
        . '<div class="text-xs uppercase tracking-wide text-slate-400">' . e($label) . '</div>'
        . '<div class="mt-1 text-2xl font-bold text-slate-900">' . e($value) . '</div>' . $sub . '</div>';
};
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Platform Overview</h1>
        <p class="mt-1 text-sm text-slate-500">System-wide health and activity across all workspaces.</p>
    </div>
    <span class="rounded-full px-3 py-1 text-sm font-medium <?= $healthCls ?>">System: <?= e($health) ?></span>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<?php if ($alerts !== []): ?>
    <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4">
        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-amber-700">Open alerts</div>
        <ul class="space-y-1 text-sm text-amber-800">
            <?php foreach ($alerts as $a): ?>
                <li>• <span class="font-medium"><?= e($a['title']) ?></span> — <?= e($a['detail']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<h2 class="mb-2 mt-4 text-sm font-semibold text-slate-700">Tenancy</h2>
<div class="grid gap-4 sm:grid-cols-4">
    <?= $card('Workspaces', (string) $m['tenants']['workspaces'], $m['tenants']['active'] . ' active') ?>
    <?= $card('Users', (string) $m['users']['total'], $m['users']['system_owners'] . ' system owner(s)') ?>
    <?= $card('MRR', $money((int) $m['subscriptions']['mrr_cents']), 'monthly recurring') ?>
    <?= $card('Active subs', (string) $m['subscriptions']['active'], $m['subscriptions']['trialing'] . ' trialing') ?>
</div>

<h2 class="mb-2 mt-6 text-sm font-semibold text-slate-700">Recruitment & AI</h2>
<div class="grid gap-4 sm:grid-cols-4">
    <?= $card('Jobs', (string) $m['recruitment']['jobs'], $m['recruitment']['published_jobs'] . ' published') ?>
    <?= $card('Applications', (string) $m['recruitment']['applications']) ?>
    <?= $card('AI runs', (string) $m['ai']['sessions'], number_format((int) $m['ai']['tokens']) . ' tokens') ?>
    <?= $card('AI cost', $money((int) $m['ai']['cost_cents'])) ?>
</div>

<h2 class="mb-2 mt-6 text-sm font-semibold text-slate-700">Automation, Integration & Health</h2>
<div class="grid gap-4 sm:grid-cols-4">
    <?= $card('Workflow runs', (string) $m['automation']['executions'], $m['automation']['failed'] . ' failed') ?>
    <?= $card('Webhook deliveries', (string) $m['integration']['webhook_deliveries'], $m['integration']['webhook_failures'] . ' failed') ?>
    <?= $card('API tokens', (string) $m['integration']['api_tokens']) ?>
    <?= $card('Errors (24h)', (string) $m['health']['errors_24h'], $m['health']['open_alerts'] . ' open alerts') ?>
</div>

<div class="mt-6">
    <a href="/diagnostics" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">Open diagnostics →</a>
</div>
