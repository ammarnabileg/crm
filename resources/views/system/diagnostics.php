<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * @var array<string, array<int, array{name:string,status:string,value:string,hint?:string}>> $groups
 * @var array{pass:int,warn:int,fail:int,total:int} $summary
 * @var string $generated
 */
// Badge styling. Uses the badge-green / badge-amber / badge-red component classes
// (all present in the compiled stylesheet). Unknown statuses fall through to the
// red treatment so problems are never shown as green.
$badgeClass = static function (string $status): string {
    return match ($status) {
        'pass'  => 'badge-green',
        'warn'  => 'badge-amber',
        default => 'badge-red',
    };
};
$badgeLabel = static fn (string $status): string => match ($status) {
    'pass'  => 'Pass',
    'warn'  => 'Warn',
    default => 'Fail',
};
// Overall headline derived from the tallies.
if (($summary['fail'] ?? 0) > 0) {
    $overall = ['class' => 'badge-red', 'label' => 'Action needed'];
} elseif (($summary['warn'] ?? 0) > 0) {
    $overall = ['class' => 'badge-amber', 'label' => 'Healthy with warnings'];
} else {
    $overall = ['class' => 'badge-green', 'label' => 'All systems healthy'];
}
?>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= e($title) ?></h1>
        <p class="mt-1 text-sm text-slate-600">Read-only health report for this installation. Generated <?= e($generated) ?>.</p>
    </div>

    <!-- Summary header -->
    <div class="card">
        <div class="card-body flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="<?= e($overall['class']) ?>"><?= e($overall['label']) ?></span>
                <span class="text-sm text-slate-500"><?= e((string) ($summary['total'] ?? 0)) ?> checks run</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="badge-green">Pass <?= e((string) ($summary['pass'] ?? 0)) ?></span>
                <span class="badge-amber">Warn <?= e((string) ($summary['warn'] ?? 0)) ?></span>
                <span class="badge-red">Fail <?= e((string) ($summary['fail'] ?? 0)) ?></span>
            </div>
        </div>
    </div>

    <!-- Grouped check cards -->
    <div class="grid gap-6 lg:grid-cols-3">
        <?php foreach ($groups as $group => $checks): ?>
            <div class="card">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <h2 class="font-semibold text-slate-900"><?= e($group) ?></h2>
                    <span class="text-xs text-slate-400"><?= e((string) count($checks)) ?> checks</span>
                </div>
                <div class="card-body">
                    <?php if (empty($checks)): ?>
                        <p class="text-sm text-slate-500">No checks in this group.</p>
                    <?php else: ?>
                        <ul class="space-y-4">
                            <?php foreach ($checks as $check): ?>
                                <?php $status = $check['status'] ?? 'fail'; ?>
                                <li class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-800"><?= e($check['name'] ?? '') ?></p>
                                        <p class="text-sm text-slate-500"><?= e($check['value'] ?? '') ?></p>
                                        <?php if (! empty($check['hint'])): ?>
                                            <p class="mt-1 text-xs text-slate-400"><?= e($check['hint']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="<?= e($badgeClass($status)) ?> shrink-0"><?= e($badgeLabel($status)) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php $this->endSection(); ?>
