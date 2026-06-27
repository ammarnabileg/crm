<?php
/**
 * Component: Select — native dropdown, Input-styled (docs/30).
 * Props:
 *   - name       string  Field name (also default id).
 *   - id         string  Element id (defaults to name).
 *   - options    array   value => label map, OR list of ['value'=>,'label'=>]. Labels escaped.
 *   - selected   mixed   Selected value. Falls back to old($name).
 *   - placeholder string Optional disabled first option (escaped).
 *   - error      bool    Truthy paints the error ring.
 *   - required   bool    Mark required (default false).
 *   - disabled   bool    Disable (default false).
 *   - class      string  Extra classes appended.
 *   - attributes array   Extra HTML attributes.
 * States: focus ring, error ring, disabled, dark mode.
 * Usage:  <?= component('select', ['name' => 'status', 'options' => ['open'=>'Open','closed'=>'Closed']]) ?>
 */
$name = $name ?? '';
$id = $id ?? $name;
$selected = $selected ?? ($name !== '' ? old($name) : null);
$options = $options ?? [];
$classes = trim('input pe-9' . (($error ?? false) ? ' input-error' : '') . ' ' . ($class ?? ''));
$extra = attrs($attributes ?? []);

// Normalise to [value => label].
$normalised = [];
foreach ($options as $key => $opt) {
    if (is_array($opt)) {
        $normalised[(string) ($opt['value'] ?? '')] = (string) ($opt['label'] ?? ($opt['value'] ?? ''));
    } else {
        $normalised[(string) $key] = (string) $opt;
    }
}
?>
<select
    <?= $name !== '' ? 'name="' . e($name) . '"' : '' ?>
    <?= $id !== '' ? 'id="' . e($id) . '"' : '' ?>
    class="<?= e($classes) ?>"
    <?= ($required ?? false) ? 'required' : '' ?>
    <?= ($disabled ?? false) ? 'disabled' : '' ?>
    <?= ($error ?? false) ? 'aria-invalid="true"' : '' ?>
    <?= $extra ?>>
    <?php if (! empty($placeholder)): ?>
        <option value="" disabled <?= ($selected === null || $selected === '') ? 'selected' : '' ?>><?= e($placeholder) ?></option>
    <?php endif; ?>
    <?php foreach ($normalised as $val => $lbl): ?>
        <option value="<?= e($val) ?>" <?= ((string) $selected === (string) $val) ? 'selected' : '' ?>><?= e($lbl) ?></option>
    <?php endforeach; ?>
</select>
