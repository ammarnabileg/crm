<?php
/**
 * Component: Textarea — multi-line text field, matching the Input treatment (docs/30).
 * Props:
 *   - name       string  Field name (also default id).
 *   - id         string  Element id (defaults to name).
 *   - value      mixed   Current value (escaped). Falls back to old($name).
 *   - placeholder string Placeholder (escaped).
 *   - rows       int     Visible rows (default 4).
 *   - error      bool    Truthy paints the error ring.
 *   - required   bool    Mark required (default false).
 *   - disabled   bool    Disable (default false).
 *   - class      string  Extra classes appended.
 *   - attributes array   Extra HTML attributes (maxlength/aria-*).
 * States: focus ring, error ring, disabled, dark mode.
 * Usage:  <?= component('textarea', ['name' => 'notes', 'rows' => 6]) ?>
 */
$name = $name ?? '';
$id = $id ?? $name;
$value = $value ?? ($name !== '' ? old($name) : null);
$classes = trim('input' . (($error ?? false) ? ' input-error' : '') . ' ' . ($class ?? ''));
$extra = attrs($attributes ?? []);
?>
<textarea
    <?= $name !== '' ? 'name="' . e($name) . '"' : '' ?>
    <?= $id !== '' ? 'id="' . e($id) . '"' : '' ?>
    rows="<?= e((string) ($rows ?? 4)) ?>"
    class="<?= e($classes) ?>"
    <?php if (! empty($placeholder)): ?>placeholder="<?= e($placeholder) ?>"<?php endif; ?>
    <?= ($required ?? false) ? 'required' : '' ?>
    <?= ($disabled ?? false) ? 'disabled' : '' ?>
    <?= ($error ?? false) ? 'aria-invalid="true"' : '' ?>
    <?= $extra ?>><?= e($value ?? '') ?></textarea>
