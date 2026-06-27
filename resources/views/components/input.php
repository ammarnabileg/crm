<?php
/**
 * Component: Input — single-line text/email/number/etc. field, design-token styled
 *            with consistent focus, error and dark-mode treatment (docs/30).
 * Props:
 *   - name       string  Field name (also default id).
 *   - id         string  Element id (defaults to name).
 *   - type       string  HTML input type (default text).
 *   - value      mixed   Current value (escaped). Falls back to old($name).
 *   - placeholder string Placeholder text (escaped).
 *   - error      bool|string  Truthy paints the error ring (string ignored here; use `field`).
 *   - required   bool    Mark required (default false).
 *   - disabled   bool    Disable (default false).
 *   - class      string  Extra classes appended.
 *   - attributes array   Extra HTML attributes (min/max/step/autocomplete/aria-*).
 * States: focus ring, error ring, disabled, placeholder, dark mode.
 * Usage:  <?= component('input', ['name' => 'title', 'placeholder' => 'Job title']) ?>
 */
$name = $name ?? '';
$id = $id ?? $name;
$value = $value ?? ($name !== '' ? old($name) : null);
$classes = trim('input' . (($error ?? false) ? ' input-error' : '') . ' ' . ($class ?? ''));
$extra = attrs($attributes ?? []);
?>
<input
    type="<?= e($type ?? 'text') ?>"
    <?= $name !== '' ? 'name="' . e($name) . '"' : '' ?>
    <?= $id !== '' ? 'id="' . e($id) . '"' : '' ?>
    class="<?= e($classes) ?>"
    <?php if (($value ?? null) !== null && $value !== ''): ?>value="<?= e($value) ?>"<?php endif; ?>
    <?php if (! empty($placeholder)): ?>placeholder="<?= e($placeholder) ?>"<?php endif; ?>
    <?= ($required ?? false) ? 'required' : '' ?>
    <?= ($disabled ?? false) ? 'disabled' : '' ?>
    <?= ($error ?? false) ? 'aria-invalid="true"' : '' ?>
    <?= $extra ?>>
