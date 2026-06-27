<?php
/**
 * Component: Skeleton — a shimmering content placeholder for loading states (docs/30).
 * Props:
 *   - lines int     When set, renders N stacked text bars (last one shorter).
 *   - class string  Classes for the single block when `lines` is not set
 *                   (default "h-4 w-full"). Set height/width here.
 * States: animated pulse; dark mode.
 * Usage:  <?= component('skeleton', ['lines'=>3]) ?>  |  component('skeleton', ['class'=>'h-32 w-full'])
 */
?>
<?php if (! empty($lines)): ?>
    <div class="space-y-2.5" aria-hidden="true">
        <?php $n = (int) $lines; for ($i = 0; $i < $n; $i++): ?>
            <div class="skeleton h-3 <?= $i === $n - 1 ? 'w-2/3' : 'w-full' ?>"></div>
        <?php endfor; ?>
    </div>
<?php else: ?>
    <div class="skeleton <?= e($class ?? 'h-4 w-full') ?>" aria-hidden="true"></div>
<?php endif; ?>
