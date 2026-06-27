<?php
/**
 * Component: Modal — a centred dialog over a dimmed backdrop. Opened by any element
 *            with data-modal-open="<id>"; closed by the ×, the backdrop, or Escape
 *            (app.js handles focus + scroll lock). Hidden until opened (docs/30).
 * Props:
 *   - id      string  Unique id used by triggers (required; hashed fallback).
 *   - title   string  Dialog heading (escaped).
 *   - slot    string  Raw HTML body (already-safe).
 *   - body    string  Alias of slot.
 *   - footer  string  Raw HTML footer, e.g. action buttons (already-safe).
 *   - size    string  sm|md|lg|xl (default md) — max width.
 *   - class   string  Extra classes appended to the panel.
 * States: hidden/open, focus-trapped, dark mode; animate-slide-up on open.
 * Usage:  <?= component('button', ['label'=>'Open', 'attributes'=>['data-modal-open'=>'m1']]) ?>
 *         <?= component('modal', ['id'=>'m1', 'title'=>'Confirm', 'slot'=>'<p>Sure?</p>']) ?>
 */
$id = $id ?? ('modal-' . substr(md5(($title ?? '') . ($slot ?? '')), 0, 8));
$body = $slot ?? ($body ?? '');
$sizes = ['sm' => 'max-w-sm', 'md' => 'max-w-lg', 'lg' => 'max-w-2xl', 'xl' => 'max-w-4xl'];
$max = $sizes[$size ?? 'md'] ?? $sizes['md'];
?>
<div id="<?= e($id) ?>" data-modal class="fixed inset-0 z-modal hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" data-modal-overlay></div>
    <div class="relative flex min-h-full items-center justify-center p-4">
        <div class="modal-panel <?= e($max) ?> w-full <?= e($class ?? '') ?>" role="dialog" aria-modal="true" <?= ! empty($title) ? 'aria-labelledby="' . e($id) . '-title"' : '' ?>>
            <div class="flex items-start justify-between gap-4">
                <?php if (! empty($title)): ?><h3 id="<?= e($id) ?>-title" class="text-lg font-semibold text-slate-900 dark:text-white"><?= e($title) ?></h3><?php endif; ?>
                <button type="button" data-modal-close aria-label="Close" class="-m-1.5 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="mt-4 text-sm text-slate-600 dark:text-slate-300"><?= $body ?></div>
            <?php if (! empty($footer)): ?><div class="mt-6 flex items-center justify-end gap-2"><?= $footer ?></div><?php endif; ?>
        </div>
    </div>
</div>
