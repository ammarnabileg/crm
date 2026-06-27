<?php
/**
 * Component: Chip — a compact, optionally-removable token (filters, tags, selected
 *            values) (docs/30).
 * Props:
 *   - label     string  Text (escaped).
 *   - icon      string  Raw SVG HTML before the label (already-safe).
 *   - removable bool    Render a × button that dispatches removal via app.js.
 *   - value     string  Identifier surfaced on the remove button (data-chip-value).
 *   - class     string  Extra classes appended.
 * States: default, removable (hoverable ×), dark mode.
 * Usage:  <?= component('chip', ['label' => 'PHP', 'removable' => true, 'value' => 'php']) ?>
 */
?>
<span class="chip <?= e($class ?? '') ?>"<?= ! empty($value) ? ' data-chip-value="' . e($value) . '"' : '' ?>>
    <?= $icon ?? '' ?><?= e($label ?? '') ?>
    <?php if ($removable ?? false): ?>
        <button type="button" data-chip-remove aria-label="Remove" class="-me-1 rounded-full p-0.5 text-slate-400 hover:bg-slate-200 hover:text-slate-600 dark:hover:bg-slate-700">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    <?php endif; ?>
</span>
