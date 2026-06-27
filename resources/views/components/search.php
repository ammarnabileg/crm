<?php
/**
 * Component: Search — a unified search field (role=search) with a leading icon and a
 *            submit-on-enter GET form, the building block for global/page search
 *            (docs/30 Search Experience). Wire `action` to a real search route.
 * Props:
 *   - action      string  Form action URL the query GETs to (default current path).
 *   - name        string  Query parameter name (default "q").
 *   - value       mixed   Current query (escaped). Falls back to old($name).
 *   - placeholder string  Placeholder (escaped, default "Search…").
 *   - autofocus   bool    Focus on load (default false).
 *   - class       string  Extra classes on the form.
 * States: empty, with value, focus ring, dark mode.
 * Usage:  <?= component('search', ['action'=>url('jobs'), 'placeholder'=>'Search jobs…']) ?>
 */
$name = $name ?? 'q';
$value = $value ?? old($name);
$action = $action ?? ('/' . ltrim(request()->path(), '/'));
?>
<form role="search" method="GET" action="<?= e($action) ?>" class="relative <?= e($class ?? '') ?>">
    <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-slate-400">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
    </span>
    <input
        type="search"
        name="<?= e($name) ?>"
        class="input ps-10"
        placeholder="<?= e($placeholder ?? 'Search…') ?>"
        aria-label="<?= e($placeholder ?? 'Search') ?>"
        <?php if (($value ?? null) !== null && $value !== ''): ?>value="<?= e($value) ?>"<?php endif; ?>
        <?= ($autofocus ?? false) ? 'autofocus' : '' ?>>
</form>
