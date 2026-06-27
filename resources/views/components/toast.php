<?php
/**
 * Component: Toast — a transient notification card. Usually created at runtime by
 *            app.js (window.HalaToast), but this partial renders a static one and
 *            documents the markup the JS clones (docs/30 Notifications).
 * Props:
 *   - variant string  success|error|warning|info (default info) — accent colour.
 *   - title   string  Bold heading (escaped).
 *   - message string  Body text (escaped).
 *   - class   string  Extra classes appended.
 * States: success/error/warning/info; dismissible; dark mode.
 * Usage:  <?= component('toast', ['variant'=>'success', 'message'=>'Saved.']) ?>
 */
$accents = [
    'success' => 'text-success-600 dark:text-success-400',
    'error'   => 'text-red-600 dark:text-red-400',
    'warning' => 'text-warning-600 dark:text-warning-400',
    'info'    => 'text-info-600 dark:text-info-400',
];
$accent = $accents[$variant ?? 'info'] ?? $accents['info'];
?>
<div class="pointer-events-auto flex w-80 max-w-full items-start gap-3 rounded-xl bg-white p-4 shadow-lg ring-1 ring-slate-200 animate-slide-up dark:bg-slate-900 dark:ring-slate-800 <?= e($class ?? '') ?>" role="status" data-toast>
    <span class="mt-0.5 shrink-0 <?= e($accent) ?>">
        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v4a1 1 0 102 0V7zm-1 7a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/></svg>
    </span>
    <div class="min-w-0 flex-1">
        <?php if (! empty($title)): ?><p class="text-sm font-semibold text-slate-800 dark:text-slate-100"><?= e($title) ?></p><?php endif; ?>
        <?php if (! empty($message)): ?><p class="text-sm text-slate-500 dark:text-slate-400"><?= e($message) ?></p><?php endif; ?>
    </div>
    <button type="button" data-toast-dismiss aria-label="Dismiss" class="-m-1 shrink-0 rounded-md p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
</div>
