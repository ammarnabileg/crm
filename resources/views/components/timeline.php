<?php
/**
 * Component: Timeline — a vertical, chronological list (activity, candidate history,
 *            audit trail). Flow-relative so the rail mirrors in RTL (docs/30).
 * Props:
 *   - items array  Each: ['title'=>string, 'time'=>?string, 'description'=>?rawHTML,
 *                  'icon'=>?rawHTML, 'variant'=>?green|amber|red|brand|slate].
 *   - class string Extra classes appended.
 * States: with/without time, description, icon; dark mode.
 * Usage:  <?= component('timeline', ['items'=>[['title'=>'Applied','time'=>'2d ago']]]) ?>
 */
$items = $items ?? [];
$dots = [
    'green' => 'bg-green-100 text-green-600 dark:bg-green-900/50 dark:text-green-300',
    'amber' => 'bg-amber-100 text-amber-600 dark:bg-amber-900/50 dark:text-amber-300',
    'red'   => 'bg-red-100 text-red-600 dark:bg-red-900/50 dark:text-red-300',
    'brand' => 'bg-brand-100 text-brand-600 dark:bg-brand-900/50 dark:text-brand-300',
    'slate' => 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300',
];
?>
<ol class="relative ms-3 border-s border-slate-200 dark:border-slate-700 <?= e($class ?? '') ?>">
    <?php foreach ($items as $item): $tone = $dots[$item['variant'] ?? 'brand'] ?? $dots['brand']; ?>
        <li class="mb-6 ms-6 last:mb-0">
            <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-white dark:ring-slate-900 <?= e($tone) ?>">
                <?php if (! empty($item['icon'])): ?><?= $item['icon'] ?><?php else: ?><span class="h-2 w-2 rounded-full bg-current"></span><?php endif; ?>
            </span>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100"><?= e($item['title'] ?? '') ?></h4>
                <?php if (! empty($item['time'])): ?><time class="text-xs text-slate-400"><?= e($item['time']) ?></time><?php endif; ?>
            </div>
            <?php if (! empty($item['description'])): ?><div class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= $item['description'] ?></div><?php endif; ?>
        </li>
    <?php endforeach; ?>
</ol>
