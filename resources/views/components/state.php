<?php
/**
 * Component: State — the unified non-content screen: Empty, Error, Permission-denied,
 *            Offline and Loading. Every list/page uses this so blank/edge states look
 *            consistent (docs/30 Empty States). Pick a `variant` for sane defaults and
 *            override any field.
 * Props:
 *   - variant string  empty|error|permission|offline|loading (default empty).
 *   - title   string  Heading (escaped) — overrides the variant default.
 *   - message string  Supporting text (escaped) — overrides the variant default.
 *   - icon    string  Raw SVG HTML (already-safe) — overrides the variant default.
 *   - action  string  Raw HTML call-to-action, e.g. a button (already-safe).
 *   - class   string  Extra classes appended.
 * States: the five variants above; dark mode.
 * Usage:  <?= component('state', ['variant'=>'empty', 'title'=>'No jobs yet', 'action'=>$btn]) ?>
 */
$svg = static fn (string $d): string => '<svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="' . $d . '"/></svg>';
$defaults = [
    'empty'      => ['title' => 'Nothing here yet', 'message' => 'There’s no data to show. Create the first item to get started.', 'icon' => $svg('M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4')],
    'error'      => ['title' => 'Something went wrong', 'message' => 'We couldn’t load this. Please try again in a moment.', 'icon' => $svg('M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-2.99l-6.93-12a2 2 0 00-3.48 0l-6.93 12A2 2 0 005.07 19z')],
    'permission' => ['title' => 'You don’t have access', 'message' => 'You don’t have permission to view this. Ask an administrator if you need it.', 'icon' => $svg('M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z')],
    'offline'    => ['title' => 'You’re offline', 'message' => 'Check your connection — we’ll retry automatically when you’re back.', 'icon' => $svg('M18.364 5.636a9 9 0 010 12.728m-12.728 0a9 9 0 010-12.728m9.9 2.829a5 5 0 010 7.07m-7.07 0a5 5 0 010-7.07M13 16a1 1 0 11-2 0 1 1 0 012 0z')],
    'loading'    => ['title' => 'Loading…', 'message' => '', 'icon' => ''],
];
$variant = $variant ?? 'empty';
$def = $defaults[$variant] ?? $defaults['empty'];
$title = $title ?? $def['title'];
$message = $message ?? $def['message'];
$tones = ['empty' => 'text-slate-400', 'error' => 'text-red-500', 'permission' => 'text-amber-500', 'offline' => 'text-slate-400', 'loading' => 'text-brand-500'];
$tone = $tones[$variant] ?? 'text-slate-400';
?>
<div class="state <?= e($class ?? '') ?>"<?= $variant === 'permission' ? ' role="alert"' : '' ?>>
    <?php if ($variant === 'loading'): ?>
        <?= component('spinner', ['size' => 'lg', 'label' => $title]) ?>
    <?php else: ?>
        <span class="<?= e($tone) ?>"><?= $icon ?? $def['icon'] ?></span>
        <p class="state-title"><?= e($title) ?></p>
        <?php if (! empty($message)): ?><p class="state-text"><?= e($message) ?></p><?php endif; ?>
        <?php if (! empty($action)): ?><div class="mt-1"><?= $action ?></div><?php endif; ?>
    <?php endif; ?>
</div>
