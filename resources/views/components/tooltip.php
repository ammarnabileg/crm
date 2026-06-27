<?php
/**
 * Component: Tooltip — a CSS-only hover/focus hint (no JS). Wraps a trigger and
 *            reveals a label on group-hover/focus (docs/30).
 * Props:
 *   - text     string  Tooltip text (escaped).
 *   - label    string  Trigger text (escaped). Used when `trigger` absent.
 *   - trigger  string  Raw HTML trigger (already-safe). Wins over label.
 *   - position string  top|bottom (default top).
 *   - class    string  Extra classes on the wrapper.
 * States: hidden, shown on hover/focus; dark mode.
 * Usage:  <?= component('tooltip', ['text'=>'Help', 'label'=>'?']) ?>
 */
$pos = ($position ?? 'top') === 'bottom'
    ? 'top-full mt-2 start-1/2 -translate-x-1/2'
    : 'bottom-full mb-2 start-1/2 -translate-x-1/2';
?>
<span class="group relative inline-flex <?= e($class ?? '') ?>" tabindex="0">
    <?php if (! empty($trigger)): ?><?= $trigger ?><?php else: ?><span class="cursor-help underline decoration-dotted underline-offset-2"><?= e($label ?? '?') ?></span><?php endif; ?>
    <span role="tooltip" class="tooltip group-focus:block <?= e($pos) ?>"><?= e($text ?? '') ?></span>
</span>
