<?php
/**
 * Component: Breadcrumb — hierarchical location trail; the last item is current
 *            (aria-current) and unlinked. Flow-relative for RTL/LTR (docs/30).
 * Props:
 *   - items  array  Ordered list of ['label' => string, 'href' => ?string] (labels escaped).
 *   - class  string Extra classes appended.
 * States: links vs current; dark mode.
 * Usage:  <?= component('breadcrumb', ['items' => [['label'=>'Jobs','href'=>url('jobs')], ['label'=>'Detail']]]) ?>
 */
$items = $items ?? [];
$last = count($items) - 1;
?>
<nav class="flex <?= e($class ?? '') ?>" aria-label="Breadcrumb">
    <ol class="flex flex-wrap items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400">
        <?php foreach ($items as $i => $item): ?>
            <li class="flex items-center gap-1.5">
                <?php if ($i > 0): ?>
                    <svg class="h-4 w-4 text-slate-300 rtl:rotate-180 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <?php endif; ?>
                <?php if ($i === $last || empty($item['href'])): ?>
                    <span class="font-medium text-slate-700 dark:text-slate-200" aria-current="page"><?= e($item['label'] ?? '') ?></span>
                <?php else: ?>
                    <a href="<?= e($item['href']) ?>" class="hover:text-brand-600 dark:hover:text-brand-300"><?= e($item['label'] ?? '') ?></a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
