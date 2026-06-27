<?php
/**
 * Component: Avatar — a person/workspace identity chip; image when available, else
 *            generated initials on a brand tint (docs/30).
 * Props:
 *   - name   string  Full name (initials derived from it; also alt/title) (escaped).
 *   - src    string  Image URL; when set, renders an <img>.
 *   - size   string  xs|sm|md|lg (default md).
 *   - class  string  Extra classes appended.
 * States: image, initials fallback, dark mode.
 * Usage:  <?= component('avatar', ['name' => 'Sara Ali', 'size' => 'sm']) ?>
 */
$sizes = [
    'xs' => 'h-6 w-6 text-[10px]',
    'sm' => 'h-8 w-8 text-xs',
    'md' => 'h-10 w-10 text-sm',
    'lg' => 'h-14 w-14 text-lg',
];
$size = $sizes[$size ?? 'md'] ?? $sizes['md'];
$name = (string) ($name ?? '');
$parts = preg_split('/\s+/', trim($name)) ?: [];
$initials = '';
foreach ($parts as $p) {
    if ($p !== '') {
        $initials .= mb_substr($p, 0, 1);
    }
    if (mb_strlen($initials) >= 2) {
        break;
    }
}
$initials = mb_strtoupper($initials !== '' ? $initials : '?');
?>
<?php if (! empty($src)): ?>
    <img src="<?= e($src) ?>" alt="<?= e($name) ?>" class="inline-block shrink-0 rounded-full object-cover <?= e($size) ?> <?= e($class ?? '') ?>">
<?php else: ?>
    <span title="<?= e($name) ?>" class="inline-flex shrink-0 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-700 dark:bg-brand-900/50 dark:text-brand-200 <?= e($size) ?> <?= e($class ?? '') ?>"><?= e($initials) ?></span>
<?php endif; ?>
