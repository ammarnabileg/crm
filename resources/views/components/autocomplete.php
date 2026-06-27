<?php
/**
 * Component: Autocomplete — an accessible combobox: a text input that filters a
 *            provided option list as you type (app.js). Submits the chosen value
 *            with the form (docs/30 Form System).
 * Props:
 *   - name        string  Field name (also default id).
 *   - id          string  Element id (defaults to name).
 *   - options     array   Suggestions: list of strings OR value=>label OR [['value','label']].
 *   - value       mixed   Current value (escaped). Falls back to old($name).
 *   - placeholder string  Placeholder (escaped).
 *   - required    bool    Mark required (default false).
 *   - class       string  Extra classes on the wrapper.
 * States: idle, open (listbox), active option, no-match, dark mode.
 * Usage:  <?= component('autocomplete', ['name'=>'skill', 'options'=>['PHP','MySQL','Tailwind']]) ?>
 */
$name = $name ?? '';
$id = $id ?? ($name !== '' ? $name : 'ac_' . substr(md5((string) ($placeholder ?? 'ac')), 0, 6));
$value = $value ?? ($name !== '' ? old($name) : null);
$options = $options ?? [];
$normalised = [];
foreach ($options as $key => $opt) {
    if (is_array($opt)) {
        $normalised[(string) ($opt['value'] ?? '')] = (string) ($opt['label'] ?? ($opt['value'] ?? ''));
    } elseif (is_int($key)) {
        $normalised[(string) $opt] = (string) $opt;
    } else {
        $normalised[(string) $key] = (string) $opt;
    }
}
?>
<div class="relative" data-autocomplete>
    <input
        type="text"
        role="combobox"
        aria-expanded="false"
        aria-autocomplete="list"
        aria-controls="<?= e($id) ?>-list"
        autocomplete="off"
        data-autocomplete-input
        <?= $name !== '' ? 'name="' . e($name) . '"' : '' ?>
        id="<?= e($id) ?>"
        class="input"
        <?php if (($value ?? null) !== null && $value !== ''): ?>value="<?= e($value) ?>"<?php endif; ?>
        <?php if (! empty($placeholder)): ?>placeholder="<?= e($placeholder) ?>"<?php endif; ?>
        <?= ($required ?? false) ? 'required' : '' ?>>
    <ul id="<?= e($id) ?>-list" role="listbox" data-autocomplete-list
        class="absolute z-dropdown mt-1 hidden max-h-56 w-full overflow-auto rounded-lg bg-white p-1 shadow-lg ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
        <?php foreach ($normalised as $val => $lbl): ?>
            <li role="option" data-value="<?= e($val) ?>"
                class="cursor-pointer rounded-md px-3 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800"><?= e($lbl) ?></li>
        <?php endforeach; ?>
        <li data-autocomplete-empty hidden class="px-3 py-2 text-sm text-slate-400">No matches</li>
    </ul>
</div>
