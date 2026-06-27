<?php
/**
 * Component: Card — the standard surface container (docs/30).
 * Props:
 *   - title   string  Optional header title (escaped); renders a card-header.
 *   - actions string  Raw HTML rendered at the end of the header (already-safe).
 *   - header  string  Raw HTML header, overrides title/actions (already-safe).
 *   - slot    string  Raw HTML body (already-safe).
 *   - body    string  Alias of slot.
 *   - footer  string  Raw HTML footer (already-safe).
 *   - padded  bool    Wrap the body in card-body padding (default true).
 *   - class   string  Extra classes appended to the card.
 * States: with/without header & footer; dark mode.
 * Usage:  <?= component('card', ['title' => 'Jobs', 'slot' => $html]) ?>
 */
$body = $slot ?? ($body ?? '');
$padded = $padded ?? true;
?>
<div class="card <?= e($class ?? '') ?>">
    <?php if (isset($header)): ?>
        <div class="card-header"><?= $header ?></div>
    <?php elseif (! empty($title) || ! empty($actions)): ?>
        <div class="card-header">
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100"><?= e($title ?? '') ?></h3>
            <?php if (! empty($actions)): ?><div class="flex items-center gap-2"><?= $actions ?></div><?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($padded): ?><div class="card-body"><?= $body ?></div><?php else: ?><?= $body ?><?php endif; ?>
    <?php if (! empty($footer)): ?>
        <div class="border-t border-slate-100 px-6 py-4 dark:border-slate-800"><?= $footer ?></div>
    <?php endif; ?>
</div>
