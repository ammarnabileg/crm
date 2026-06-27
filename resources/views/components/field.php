<?php
/**
 * Component: Field — the unified form row: label + control + hint + error, with
 *            correct label/for + aria-describedby wiring (docs/30 Form System).
 * Props:
 *   - label    string  Label text (escaped).
 *   - for      string  id of the control the label points at.
 *   - control  string  Raw HTML of the control (already-safe — pass a component()).
 *   - hint     string  Helper text under the control (escaped).
 *   - error    string  Validation message; when set, shows red text. Falls back to
 *                      the flashed errors bag for `name` if `error` is omitted.
 *   - name     string  Field name (used to pull the flashed error automatically).
 *   - required bool    Append a required asterisk to the label (default false).
 *   - class    string  Extra classes on the wrapper.
 * States: default, error (message + aria), with-hint.
 * Usage:
 *   <?= component('field', [
 *       'label' => 'Title', 'for' => 'title', 'name' => 'title',
 *       'control' => component('input', ['name' => 'title', 'id' => 'title']),
 *   ]) ?>
 */
$name = $name ?? '';
$error = $error ?? null;
if ($error === null && $name !== '') {
    $bag = session()->get('errors', []);
    if (! empty($bag[$name])) {
        $error = is_array($bag[$name]) ? (string) reset($bag[$name]) : (string) $bag[$name];
    }
}
$for = $for ?? ($name !== '' ? $name : null);
$hintId = $for ? $for . '-hint' : null;
$errId = $for ? $for . '-error' : null;
?>
<div class="<?= e($class ?? '') ?>">
    <?php if (! empty($label)): ?>
        <label class="label" <?= $for ? 'for="' . e($for) . '"' : '' ?>>
            <?= e($label) ?><?php if ($required ?? false): ?><span class="text-red-500"> *</span><?php endif; ?>
        </label>
    <?php endif; ?>
    <?= $control ?? '' ?>
    <?php if (! empty($hint) && ! $error): ?>
        <p class="hint" <?= $hintId ? 'id="' . e($hintId) . '"' : '' ?>><?= e($hint) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="form-error" <?= $errId ? 'id="' . e($errId) . '"' : '' ?>><?= e($error) ?></p>
    <?php endif; ?>
</div>
