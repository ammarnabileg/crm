<?php
/**
 * Component: File upload — a dropzone wrapping a native file input. Drag-and-drop and
 *            the selected-file list are progressive enhancement (app.js); the input
 *            submits with the form natively (docs/30 File Upload). The upload endpoint,
 *            storage and server-side validation are the page's responsibility.
 * Props:
 *   - name     string  Field name (also default id). Use name[] with multiple.
 *   - id       string  Element id (defaults to name).
 *   - accept   string  Accepted types, e.g. ".pdf,image/*".
 *   - multiple bool    Allow multiple files (default false).
 *   - label    string  Primary dropzone text (escaped).
 *   - hint     string  Secondary hint, e.g. "PDF up to 5MB" (escaped).
 *   - required bool    Mark required (default false).
 *   - class    string  Extra classes appended.
 * States: idle, drag-over (highlight), files selected, dark mode.
 * Usage:  <?= component('file-upload', ['name'=>'cv', 'accept'=>'.pdf', 'hint'=>'PDF up to 5MB']) ?>
 */
$name = $name ?? '';
$id = $id ?? ($name !== '' ? rtrim($name, '[]') : 'fu_' . substr(md5((string) ($label ?? 'fu')), 0, 6));
?>
<label data-dropzone class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 p-8 text-center transition hover:border-brand-400 hover:bg-slate-50 dark:border-slate-700 dark:hover:border-brand-500 dark:hover:bg-slate-800/50 <?= e($class ?? '') ?>">
    <svg class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.9A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
    <span class="text-sm font-medium text-slate-700 dark:text-slate-200"><?= e($label ?? 'Drag & drop a file, or click to browse') ?></span>
    <?php if (! empty($hint)): ?><span class="text-xs text-slate-400"><?= e($hint) ?></span><?php endif; ?>
    <input
        type="file"
        <?= $name !== '' ? 'name="' . e($name) . '"' : '' ?>
        id="<?= e($id) ?>"
        class="sr-only"
        data-dropzone-input
        <?php if (! empty($accept)): ?>accept="<?= e($accept) ?>"<?php endif; ?>
        <?= ($multiple ?? false) ? 'multiple' : '' ?>
        <?= ($required ?? false) ? 'required' : '' ?>>
    <ul class="mt-2 w-full space-y-1 text-sm text-slate-600 dark:text-slate-300" data-dropzone-files></ul>
</label>
