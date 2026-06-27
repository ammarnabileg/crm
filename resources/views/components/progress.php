<?php
/**
 * Component: Progress — a determinate progress bar with an accessible role (docs/30).
 * Props:
 *   - value     int     Percentage 0–100 (clamped).
 *   - label     string  Optional caption shown above the bar (escaped).
 *   - showValue bool    Show the percentage next to the label (default false).
 *   - variant   string  brand|success|warning|danger|info (default brand) — bar colour.
 *   - class     string  Extra classes appended.
 * States: any value; dark mode.
 * Usage:  <?= component('progress', ['value'=>70, 'label'=>'Profile', 'showValue'=>true]) ?>
 */
$value = max(0, min(100, (int) ($value ?? 0)));
$colors = [
    'brand' => 'bg-brand-600', 'success' => 'bg-success-600', 'warning' => 'bg-warning-500',
    'danger' => 'bg-red-600', 'info' => 'bg-info-600',
];
$bar = $colors[$variant ?? 'brand'] ?? $colors['brand'];
?>
<div class="<?= e($class ?? '') ?>">
    <?php if (! empty($label) || ($showValue ?? false)): ?>
        <div class="mb-1.5 flex items-center justify-between text-xs font-medium text-slate-600 dark:text-slate-300">
            <span><?= e($label ?? '') ?></span>
            <?php if ($showValue ?? false): ?><span><?= e((string) $value) ?>%</span><?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="progress-track" role="progressbar" aria-valuenow="<?= e((string) $value) ?>" aria-valuemin="0" aria-valuemax="100"<?= ! empty($label) ? ' aria-label="' . e($label) . '"' : '' ?>>
        <div class="progress-bar <?= e($bar) ?>" style="width: <?= e((string) $value) ?>%"></div>
    </div>
</div>
