<?php
/**
 * Component: Switch — accessible on/off toggle (ARIA switch) backed by a hidden
 *            input so it submits with a form. Toggled by app.js (docs/30).
 * Props:
 *   - name      string  Hidden-input name (its value flips 0/1 on toggle).
 *   - on        bool    Initial state (default false).
 *   - label     string  Visible label text (escaped).
 *   - value_on  string  Submitted value when on (default "1").
 *   - value_off string  Submitted value when off (default "0").
 *   - disabled  bool    Disable (default false).
 *   - class     string  Extra classes on the wrapper.
 * States: on (brand track), off, focus ring, disabled, dark mode.
 * Usage:  <?= component('switch', ['name' => 'notify', 'label' => 'Email me', 'on' => true]) ?>
 */
$name = $name ?? '';
$on = (bool) ($on ?? false);
$valueOn = $value_on ?? '1';
$valueOff = $value_off ?? '0';
$disabled = $disabled ?? false;
?>
<label class="inline-flex items-center gap-3 text-sm text-slate-700 dark:text-slate-300 <?= e($class ?? '') ?>">
    <button
        type="button"
        role="switch"
        aria-checked="<?= $on ? 'true' : 'false' ?>"
        data-switch
        data-value-on="<?= e($valueOn) ?>"
        data-value-off="<?= e($valueOff) ?>"
        class="switch"
        <?= $disabled ? 'disabled aria-disabled="true"' : '' ?>>
        <span class="switch-thumb"></span>
    </button>
    <?php if ($name !== ''): ?>
        <input type="hidden" name="<?= e($name) ?>" value="<?= e($on ? $valueOn : $valueOff) ?>">
    <?php endif; ?>
    <?php if (! empty($label)): ?><span><?= e($label) ?></span><?php endif; ?>
</label>
