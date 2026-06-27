<?php
/**
 * Component: Radio — single choice within a group sharing one `name` (docs/30).
 * Props:
 *   - name      string  Group name (required for grouping).
 *   - id        string  Element id (defaults to name_value).
 *   - label     string  Inline label text (escaped).
 *   - value     string  Submitted value.
 *   - checked   bool    Checked state (default false).
 *   - disabled  bool    Disable (default false).
 *   - class     string  Extra classes on the wrapper.
 *   - attributes array  Extra HTML attributes on the input.
 * States: checked, focus ring, disabled, dark mode.
 * Usage:  <?= component('radio', ['name' => 'plan', 'value' => 'pro', 'label' => 'Pro']) ?>
 */
$name = $name ?? '';
$value = (string) ($value ?? '');
$id = $id ?? trim($name . '_' . $value, '_');
$extra = attrs($attributes ?? []);
?>
<label class="inline-flex items-center gap-2.5 text-sm text-slate-700 dark:text-slate-300 <?= e($class ?? '') ?>">
    <input
        type="radio"
        <?= $name !== '' ? 'name="' . e($name) . '"' : '' ?>
        id="<?= e($id) ?>"
        value="<?= e($value) ?>"
        class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800"
        <?= ($checked ?? false) ? 'checked' : '' ?>
        <?= ($disabled ?? false) ? 'disabled' : '' ?>
        <?= $extra ?>>
    <?php if (! empty($label)): ?><span><?= e($label) ?></span><?php endif; ?>
</label>
