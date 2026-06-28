<?php
/** @var array{status: object, probes: array<string,array{status:string,severity:string,message:string}>} $health */
/** @var list<array{key:string,label:string,status:string,items:list<array{k:string,v:string}>}> $panels */
/** @var list<array<string,mixed>> $errors */
/** @var list<array<string,mixed>> $alerts */
/** @var list<array<string,mixed>> $backups */
/** @var string|null $status */

$overall = $health['status']->value;
$overallCls = $overall === 'healthy' ? 'bg-emerald-50 text-emerald-700' : ($overall === 'degraded' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700');
$dot = static fn (string $s): string => $s === 'healthy' ? 'text-emerald-500' : ($s === 'degraded' ? 'text-amber-500' : 'text-rose-500');
$panelBadge = static fn (string $s): string => match ($s) {
    'ok' => 'bg-emerald-50 text-emerald-700',
    'warn' => 'bg-amber-50 text-amber-700',
    'down' => 'bg-rose-50 text-rose-700',
    default => 'bg-slate-100 text-slate-500',
};
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Diagnostics</h1>
        <p class="mt-1 text-sm text-slate-500">Health probes, captured errors, active alerts, and backups.</p>
    </div>
    <span class="rounded-full px-3 py-1 text-sm font-medium <?= $overallCls ?>"><?= e($overall) ?></span>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<!-- Infrastructure panels (read-only, observed facts) -->
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($panels as $panel): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-900"><?= e($panel['label']) ?></h3>
                <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $panelBadge($panel['status']) ?>"><?= e($panel['status']) ?></span>
            </div>
            <dl class="space-y-1.5 text-xs">
                <?php foreach ($panel['items'] as $item): ?>
                    <div class="flex items-start justify-between gap-2">
                        <dt class="shrink-0 text-slate-400"><?= e($item['k']) ?></dt>
                        <dd class="text-right font-medium text-slate-700"><?= e($item['v']) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <!-- Health probes -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Health probes</h2>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($health['probes'] as $name => $p): ?>
                <li class="flex items-center justify-between px-5 py-3 text-sm">
                    <div>
                        <span class="<?= $dot($p['status']) ?>">●</span>
                        <span class="font-medium text-slate-700"><?= e($name) ?></span>
                        <span class="text-xs text-slate-400">(<?= e($p['severity']) ?>)</span>
                        <div class="ms-4 text-xs text-slate-400"><?= e($p['message']) ?></div>
                    </div>
                    <span class="text-xs font-medium <?= $dot($p['status']) ?>"><?= e($p['status']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Open alerts -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Open alerts</h2>
        <?php if ($alerts === []): ?>
            <p class="px-5 py-6 text-sm text-slate-400">No open alerts. All monitors are green.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($alerts as $a): ?>
                    <li class="px-5 py-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-slate-800"><?= e($a['title']) ?></span>
                            <?php $sc = (string) $a['severity'] === 'critical' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700'; ?>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $sc ?>"><?= e($a['severity']) ?></span>
                        </div>
                        <div class="text-xs text-slate-400"><?= e($a['detail']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- Recent errors -->
<div class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
    <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Recent errors</h2>
    <?php if ($errors === []): ?>
        <p class="px-5 py-6 text-sm text-slate-400">No errors captured. 🎉</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($errors as $err): ?>
                <li class="px-5 py-3 text-sm">
                    <div class="font-mono text-xs text-rose-600"><?= e($err['exception_class'] ?? 'Error') ?></div>
                    <div class="text-slate-700"><?= e($err['message']) ?></div>
                    <div class="text-xs text-slate-400"><?= e($err['file'] ?? '') ?>:<?= e($err['line'] ?? '') ?> · <?= e($err['occurred_at']) ?> UTC</div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<!-- Backups -->
<div class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
        <h2 class="text-sm font-semibold text-slate-900">Backups</h2>
        <form method="post" action="/admin/diagnostics/backup">
            <?= csrf_field() ?>
            <button class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">Run backup now</button>
        </form>
    </div>
    <?php if ($backups === []): ?>
        <p class="px-5 py-6 text-sm text-slate-400">No backups yet.</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($backups as $b): ?>
                <li class="flex items-center justify-between px-5 py-3 text-sm">
                    <div>
                        <span class="font-medium text-slate-700"><?= e($b['kind']) ?></span>
                        <span class="text-slate-400">· <?= e($b['tables_count'] ?? 0) ?> tables · <?= e(number_format(((int) ($b['size_bytes'] ?? 0)) / 1024, 1)) ?> KB</span>
                        <div class="text-xs text-slate-400"><?= e($b['started_at']) ?> UTC</div>
                    </div>
                    <?php $bc = (string) $b['status'] === 'completed' ? 'bg-emerald-50 text-emerald-700' : ((string) $b['status'] === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-500'); ?>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $bc ?>"><?= e($b['status']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
