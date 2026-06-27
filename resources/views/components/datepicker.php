<?php
/**
 * Component: DatePicker — a native, localised date field styled to match the Design
 *            System. Native input = a real picker on every device, no heavy JS, and
 *            correct in RTL/LTR (docs/30 Form System).
 * Props:
 *   - name     string  Field name (also default id).
 *   - id       string  Element id (defaults to name).
 *   - value    string  Current value (YYYY-MM-DD). Falls back to old($name).
 *   - min      string  Earliest selectable date (YYYY-MM-DD).
 *   - max      string  Latest selectable date (YYYY-MM-DD).
 *   - type     string  date|datetime-local|time|month (default date).
 *   - error    bool    Truthy paints the error ring.
 *   - required bool    Mark required (default false).
 *   - disabled bool    Disable (default false).
 *   - class    string  Extra classes appended.
 * States: focus ring, error ring, disabled, dark mode.
 * Usage:  <?= component('datepicker', ['name'=>'start', 'min'=>'2026-01-01']) ?>
 */
$name = $name ?? '';
$id = $id ?? $name;
$value = $value ?? ($name !== '' ? old($name) : null);
$classes = trim('input' . (($error ?? false) ? ' input-error' : '') . ' [color-scheme:light] dark:[color-scheme:dark] ' . ($class ?? ''));
?>
<input
    type="<?= e($type ?? 'date') ?>"
    <?= $name !== '' ? 'name="' . e($name) . '"' : '' ?>
    <?= $id !== '' ? 'id="' . e($id) . '"' : '' ?>
    class="<?= e($classes) ?>"
    <?php if (($value ?? null) !== null && $value !== ''): ?>value="<?= e($value) ?>"<?php endif; ?>
    <?php if (! empty($min)): ?>min="<?= e($min) ?>"<?php endif; ?>
    <?php if (! empty($max)): ?>max="<?= e($max) ?>"<?php endif; ?>
    <?= ($required ?? false) ? 'required' : '' ?>
    <?= ($disabled ?? false) ? 'disabled' : '' ?>
    <?= ($error ?? false) ? 'aria-invalid="true"' : '' ?>>
