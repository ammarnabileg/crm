<?php
/**
 * Component: Spinner — an indeterminate loading indicator with an accessible label
 *            (docs/30). Inline by default.
 * Props:
 *   - size  string  sm|md|lg (default md).
 *   - label string  Screen-reader / inline text (default "Loading…").
 *   - inline bool   When false, centres in a padded block (default true).
 *   - class string  Extra classes appended.
 * States: spinning; dark mode (uses currentColor).
 * Usage:  <?= component('spinner', ['label'=>'Saving…']) ?>
 */
$sizes = ['sm' => 'h-4 w-4', 'md' => 'h-6 w-6', 'lg' => 'h-8 w-8'];
$dim = $sizes[$size ?? 'md'] ?? $sizes['md'];
$label = $label ?? 'Loading…';
$wrap = ($inline ?? true) ? 'inline-flex items-center gap-2' : 'flex flex-col items-center justify-center gap-3 py-10';
?>
<span role="status" class="<?= e($wrap) ?> text-slate-500 dark:text-slate-400 <?= e($class ?? '') ?>">
    <svg class="<?= e($dim) ?> animate-spin text-brand-600 dark:text-brand-400" fill="none" viewBox="0 0 24 24" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
    </svg>
    <span class="<?= ($inline ?? true) ? 'text-sm' : 'text-sm' ?>"><?= e($label) ?></span>
</span>
