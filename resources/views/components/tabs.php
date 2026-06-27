<?php
/**
 * Component: Tabs — accessible tabbed panels (ARIA tablist/tab/tabpanel). app.js
 *            switches panels and supports arrow-key navigation (docs/30).
 * Props:
 *   - id    string  Unique group id (required; hashed fallback).
 *   - tabs  array   Ordered list of ['label'=>string, 'panel'=>rawHTML(already-safe)].
 *   - active int     Index of the initially-selected tab (default 0).
 *   - class string  Extra classes appended to the wrapper.
 * States: selected vs idle tab, keyboard focus, dark mode.
 * Usage:  <?= component('tabs', ['id'=>'t1', 'tabs'=>[['label'=>'A','panel'=>'<p>A</p>'], ...]]) ?>
 */
$tabs = $tabs ?? [];
$id = $id ?? ('tabs-' . substr(md5(json_encode(array_column($tabs, 'label')) ?: 't'), 0, 8));
$active = (int) ($active ?? 0);
?>
<div data-tabs class="<?= e($class ?? '') ?>">
    <div role="tablist" class="flex gap-6 overflow-x-auto border-b border-slate-200 dark:border-slate-800">
        <?php foreach ($tabs as $i => $tab): $on = $i === $active; ?>
            <button type="button" role="tab"
                    id="<?= e($id) ?>-tab-<?= $i ?>"
                    aria-controls="<?= e($id) ?>-panel-<?= $i ?>"
                    aria-selected="<?= $on ? 'true' : 'false' ?>"
                    tabindex="<?= $on ? '0' : '-1' ?>"
                    data-tab-target="<?= e($id) ?>-panel-<?= $i ?>"
                    class="tab"><?= e($tab['label'] ?? ('Tab ' . ($i + 1))) ?></button>
        <?php endforeach; ?>
    </div>
    <?php foreach ($tabs as $i => $tab): $on = $i === $active; ?>
        <div role="tabpanel" id="<?= e($id) ?>-panel-<?= $i ?>" aria-labelledby="<?= e($id) ?>-tab-<?= $i ?>" data-tab-panel class="pt-4 text-sm text-slate-600 dark:text-slate-300" <?= $on ? '' : 'hidden' ?>><?= $tab['panel'] ?? '' ?></div>
    <?php endforeach; ?>
</div>
