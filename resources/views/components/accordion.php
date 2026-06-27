<?php
/**
 * Component: Accordion — stacked, collapsible sections built on native <details>
 *            (works with JS disabled). Chevron rotates on open (docs/30).
 * Props:
 *   - items  array  Ordered list of ['title'=>string, 'content'=>rawHTML, 'open'=>?bool].
 *   - class  string Extra classes appended to the wrapper.
 * States: open/closed (native), hover, dark mode.
 * Usage:  <?= component('accordion', ['items'=>[['title'=>'Q1','content'=>'<p>A1</p>']]]) ?>
 */
$items = $items ?? [];
?>
<div class="divide-y divide-slate-200 overflow-hidden rounded-xl border border-slate-200 dark:divide-slate-800 dark:border-slate-800 <?= e($class ?? '') ?>">
    <?php foreach ($items as $item): ?>
        <details class="group" <?= ! empty($item['open']) ? 'open' : '' ?>>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-slate-800 hover:bg-slate-50 dark:text-slate-100 dark:hover:bg-slate-800/60">
                <span><?= e($item['title'] ?? '') ?></span>
                <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </summary>
            <div class="px-4 pb-4 text-sm text-slate-600 dark:text-slate-300"><?= $item['content'] ?? '' ?></div>
        </details>
    <?php endforeach; ?>
</div>
