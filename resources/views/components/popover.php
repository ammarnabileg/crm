<?php
/**
 * Component: Popover — a click-triggered floating panel of arbitrary content, built
 *            on native <details> so it works without JS (app.js closes it on
 *            outside-click / Escape). Flow-relative alignment (docs/30).
 * Props:
 *   - label   string  Trigger button text (escaped). Used when `trigger` absent.
 *   - trigger string  Raw HTML trigger (already-safe). Wins over label.
 *   - slot    string  Raw HTML panel content (already-safe).
 *   - align   string  start|end (default start).
 *   - width   string  Tailwind width class for the panel (default w-72).
 *   - class   string  Extra classes on the <details> wrapper.
 * States: open/closed, dark mode.
 * Usage:  <?= component('popover', ['label'=>'Details', 'slot'=>'<p>Hello</p>']) ?>
 */
$align = ($align ?? 'start') === 'end' ? 'end-0' : 'start-0';
$width = $width ?? 'w-72';
?>
<details class="relative inline-block <?= e($class ?? '') ?>" data-popover>
    <summary class="list-none">
        <?php if (! empty($trigger)): ?><?= $trigger ?><?php else: ?>
            <span class="btn-secondary cursor-pointer"><?= e($label ?? 'Open') ?></span>
        <?php endif; ?>
    </summary>
    <div class="absolute <?= e($align) ?> <?= e($width) ?> z-popover mt-2 rounded-xl bg-white p-4 text-sm text-slate-600 shadow-lg ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-800">
        <?= $slot ?? '' ?>
    </div>
</details>
