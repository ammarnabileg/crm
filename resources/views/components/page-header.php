<?php
/**
 * Component: Page header — the unified page title block: optional breadcrumb, a
 *            title + subtitle, and a right-aligned actions area (docs/30 Page Layouts).
 * Props:
 *   - title      string  Page title (escaped).
 *   - subtitle   string  Supporting line under the title (escaped).
 *   - breadcrumb string  Raw HTML breadcrumb (already-safe — pass component('breadcrumb')).
 *   - actions    string  Raw HTML actions (buttons) (already-safe).
 *   - class      string  Extra classes appended.
 * States: with/without subtitle, breadcrumb, actions; responsive stack; dark mode.
 * Usage:  <?= component('page-header', ['title' => 'Jobs', 'actions' => component('button', [...])]) ?>
 */
?>
<div class="mb-6 <?= e($class ?? '') ?>">
    <?php if (! empty($breadcrumb)): ?><div class="mb-2"><?= $breadcrumb ?></div><?php endif; ?>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h1 class="truncate text-xl font-bold text-slate-900 dark:text-white sm:text-2xl"><?= e($title ?? '') ?></h1>
            <?php if (! empty($subtitle)): ?><p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= e($subtitle) ?></p><?php endif; ?>
        </div>
        <?php if (! empty($actions)): ?><div class="flex shrink-0 items-center gap-2"><?= $actions ?></div><?php endif; ?>
    </div>
</div>
