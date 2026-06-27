<?php
/**
 * Component: Drawer — an off-canvas panel sliding in from the inline edge. Opened by
 *            data-drawer-open="<id>"; closed by ×, backdrop or Escape (app.js).
 *            Flow-relative (end by default) so it mirrors in RTL (docs/30).
 * Props:
 *   - id     string  Unique id used by triggers (required; hashed fallback).
 *   - title  string  Heading (escaped).
 *   - slot   string  Raw HTML body (already-safe).
 *   - body   string  Alias of slot.
 *   - footer string  Raw HTML footer (already-safe).
 *   - side   string  start|end (default end).
 *   - class  string  Extra classes appended to the panel.
 * States: hidden/open, focus-trapped, dark mode; animate-slide-in-end.
 * Usage:  <?= component('drawer', ['id'=>'d1', 'title'=>'Filters', 'slot'=>$html]) ?>
 */
$id = $id ?? ('drawer-' . substr(md5(($title ?? '') . ($slot ?? '')), 0, 8));
$body = $slot ?? ($body ?? '');
$side = ($side ?? 'end') === 'start' ? 'start-0' : 'end-0';
?>
<div id="<?= e($id) ?>" data-drawer class="fixed inset-0 z-drawer hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" data-drawer-overlay></div>
    <div class="absolute inset-y-0 <?= e($side) ?> flex max-w-full">
        <div class="drawer-panel <?= e($class ?? '') ?>" role="dialog" aria-modal="true" <?= ! empty($title) ? 'aria-labelledby="' . e($id) . '-title"' : '' ?>>
            <div class="flex items-start justify-between gap-4">
                <?php if (! empty($title)): ?><h3 id="<?= e($id) ?>-title" class="text-lg font-semibold text-slate-900 dark:text-white"><?= e($title) ?></h3><?php endif; ?>
                <button type="button" data-drawer-close aria-label="Close" class="-m-1.5 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="mt-4 text-sm text-slate-600 dark:text-slate-300"><?= $body ?></div>
            <?php if (! empty($footer)): ?><div class="mt-6 flex items-center gap-2"><?= $footer ?></div><?php endif; ?>
        </div>
    </div>
</div>
