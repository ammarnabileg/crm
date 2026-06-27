<?php
/**
 * Component: Dropdown — a menu disclosure built on native <details> (app.js closes
 *            it on outside-click / Escape). Flow-relative alignment (docs/30).
 * Props:
 *   - label   string  Trigger button text (escaped). Used when `trigger` absent.
 *   - trigger string  Raw HTML trigger (already-safe). Wins over label.
 *   - items   array   Each: ['label'=>, 'href'=>?, 'icon'=>?rawHTML, 'danger'=>?bool]
 *                     or ['divider'=>true]. Form items: ['label'=>,'form'=>rawHTML].
 *   - align   string  start|end (default end).
 *   - class   string  Extra classes on the <details> wrapper.
 * States: open/closed, item hover, danger item, dark mode.
 * Usage:  <?= component('dropdown', ['label'=>'Actions', 'items'=>[['label'=>'Edit','href'=>'#']]]) ?>
 */
$items = $items ?? [];
$align = ($align ?? 'end') === 'start' ? 'start-0' : 'end-0';
?>
<details class="relative inline-block <?= e($class ?? '') ?>">
    <summary class="list-none">
        <?php if (! empty($trigger)): ?><?= $trigger ?><?php else: ?>
            <span class="btn-secondary cursor-pointer">
                <?= e($label ?? 'Menu') ?>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </span>
        <?php endif; ?>
    </summary>
    <div data-flyout class="absolute <?= e($align) ?> z-dropdown mt-2 w-56 max-w-[calc(100vw-2rem)] rounded-xl bg-white p-1.5 shadow-lg ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800" role="menu">
        <?php foreach ($items as $item): ?>
            <?php if (! empty($item['divider'])): ?>
                <div class="my-1.5 border-t border-slate-100 dark:border-slate-800"></div>
            <?php elseif (! empty($item['form'])): ?>
                <?= $item['form'] ?>
            <?php else: $danger = ! empty($item['danger']); ?>
                <a href="<?= e($item['href'] ?? '#') ?>" role="menuitem" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm <?= $danger ? 'text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/30' : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800' ?>">
                    <?= $item['icon'] ?? '' ?><?= e($item['label'] ?? '') ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</details>
