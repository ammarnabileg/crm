<?php
/**
 * Component: Checkbox — boolean toggle with an inline, clickable label (docs/30).
 * Props:
 *   - name      string  Field name (also default id).
 *   - id        string  Element id (defaults to name).
 *   - label     string  Inline label text (escaped).
 *   - value     string  Submitted value when checked (default "1").
 *   - checked   bool    Checked state (default false).
 *   - disabled  bool    Disable (default false).
 *   - class     string  Extra classes on the wrapper.
 *   - attributes array  Extra HTML attributes on the input.
 * States: checked, focus ring, disabled, dark mode.
 * Usage:  <?= component('checkbox', ['name' => 'remember', 'label' => 'Remember me']) ?>
 */
$name = $name ?? '';
$id = $id ?? ($name !== '' ? $name : 'cb_' . substr(md5($label ?? 'cb'), 0, 6));
$extra = attrs($attributes ?? []);
?>
<label class="inline-flex items-center gap-2.5 text-sm text-slate-700 dark:text-slate-300 <?= e($class ?? '') ?>">
    <input
        type="checkbox"
        <?= $name !== '' ? 'name="' . e($name) . '"' : '' ?>
        id="<?= e($id) ?>"
        value="<?= e($value ?? '1') ?>"
        class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800"
        <?= ($checked ?? false) ? 'checked' : '' ?>
        <?= ($disabled ?? false) ? 'disabled' : '' ?>
        <?= $extra ?>>
    <?php if (! empty($label)): ?><span><?= e($label) ?></span><?php endif; ?>
</label>
