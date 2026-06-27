<?php
/**
 * Component: Badge — a small status/label pill (docs/30).
 * Props:
 *   - label   string  Text (escaped). Optional when `slot` given.
 *   - slot    string  Raw HTML (already-safe). Wins over label.
 *   - variant string  green|amber|red|slate|brand|info (default slate).
 *   - icon    string  Raw SVG HTML before the label (already-safe).
 *   - dot     bool    Show a leading status dot (default false).
 *   - class   string  Extra classes appended.
 * States: static; dark mode aware.
 * Usage:  <?= component('badge', ['label' => 'Open', 'variant' => 'green']) ?>
 */
$variants = [
    'green' => 'badge-green', 'amber' => 'badge-amber', 'red' => 'badge-red',
    'slate' => 'badge-slate', 'brand' => 'badge-brand', 'info' => 'badge-info',
];
$dots = [
    'green' => 'bg-green-500', 'amber' => 'bg-amber-500', 'red' => 'bg-red-500',
    'slate' => 'bg-slate-400', 'brand' => 'bg-brand-500', 'info' => 'bg-info-500',
];
$key = $variant ?? 'slate';
$cls = $variants[$key] ?? $variants['slate'];
?>
<span class="<?= e($cls) ?> <?= e($class ?? '') ?>">
    <?php if ($dot ?? false): ?><span class="h-1.5 w-1.5 rounded-full <?= e($dots[$key] ?? $dots['slate']) ?>"></span><?php endif; ?>
    <?= $icon ?? '' ?><?= isset($slot) ? $slot : e($label ?? '') ?>
</span>
