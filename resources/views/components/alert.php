<?php
/**
 * Component: Alert — an inline, contextual message banner (docs/30).
 * Props:
 *   - variant     string  success|warning|error|info (default info).
 *   - title       string  Bold heading (escaped).
 *   - message     string  Body text (escaped). Optional when `slot` given.
 *   - slot        string  Raw HTML body (already-safe). Wins over message.
 *   - dismissible bool    Render a × that removes the alert via app.js (default false).
 *   - class       string  Extra classes appended.
 * States: success/warning/error/info; dismissible; dark mode.
 * Usage:  <?= component('alert', ['variant' => 'success', 'message' => 'Saved.']) ?>
 */
$variants = [
    'success' => ['cls' => 'alert-success', 'icon' => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>'],
    'warning' => ['cls' => 'alert-warning', 'icon' => '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>'],
    'error'   => ['cls' => 'alert-error',   'icon' => '<path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>'],
    'info'    => ['cls' => 'alert-info',    'icon' => '<path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-8-3a1 1 0 100 2 1 1 0 000-2zm1 4a1 1 0 10-2 0v4a1 1 0 102 0v-4z" clip-rule="evenodd"/>'],
];
$v = $variants[$variant ?? 'info'] ?? $variants['info'];
?>
<div class="<?= e($v['cls']) ?> <?= e($class ?? '') ?>" role="alert"<?= ($dismissible ?? false) ? ' data-dismissible' : '' ?>>
    <svg class="h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><?= $v['icon'] ?></svg>
    <div class="min-w-0 flex-1">
        <?php if (! empty($title)): ?><p class="font-semibold"><?= e($title) ?></p><?php endif; ?>
        <?php if (isset($slot)): ?><?= $slot ?><?php elseif (! empty($message)): ?><p><?= e($message) ?></p><?php endif; ?>
    </div>
    <?php if ($dismissible ?? false): ?>
        <button type="button" data-alert-dismiss aria-label="Dismiss" class="-m-1 shrink-0 rounded-md p-1 opacity-70 hover:opacity-100">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    <?php endif; ?>
</div>
