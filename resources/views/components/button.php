<?php
/**
 * Component: Button — the primary actionable control. One look across every
 *            variant/size/state, light & dark, RTL/LTR (docs/30 Design System).
 * Props:
 *   - label      string  Text (escaped). Optional when `slot` is given.
 *   - slot       string  Raw HTML inside the button (already-safe). Wins over label.
 *   - icon       string  Raw SVG HTML rendered before the label (already-safe).
 *   - variant    string  primary|secondary|success|warning|danger|ghost (default primary).
 *   - size       string  sm|md|lg (default md).
 *   - type       string  button|submit|reset (default button).
 *   - href       string  Render an <a> styled as a button instead of <button>.
 *   - disabled   bool    Disable (default false).
 *   - block      bool    Full width (default false).
 *   - confirm    string  Native confirm() message before a destructive submit.
 *   - class      string  Extra classes appended.
 *   - attributes array   Extra HTML attributes (name, value, id, data-, aria-).
 * States: hover, focus-visible ring, disabled (opacity + not-allowed), dark mode.
 * Usage:  <?= component('button', ['label' => 'Save', 'type' => 'submit']) ?>
 */
$variants = [
    'primary'   => 'btn-primary',
    'secondary' => 'btn-secondary',
    'success'   => 'btn-success',
    'warning'   => 'btn-warning',
    'danger'    => 'btn-danger',
    'ghost'     => 'btn-ghost',
];
$sizes = ['sm' => 'btn-sm', 'md' => '', 'lg' => 'btn-lg'];

$variant = $variants[$variant ?? 'primary'] ?? $variants['primary'];
$size = $sizes[$size ?? 'md'] ?? '';
$classes = trim($variant . ' ' . $size . (($block ?? false) ? ' w-full' : '') . ' ' . ($class ?? ''));
$inner = ($icon ?? '') . (isset($slot) ? $slot : e($label ?? ''));
$extra = attrs($attributes ?? []);
$disabled = $disabled ?? false;
$confirm = $confirm ?? null;
?>
<?php if (! empty($href)): ?>
    <a href="<?= e($href) ?>" class="<?= e($classes) ?><?= $disabled ? ' pointer-events-none opacity-60' : '' ?>"<?= $disabled ? ' aria-disabled="true"' : '' ?><?= $extra ?>><?= $inner ?></a>
<?php else: ?>
    <button type="<?= e($type ?? 'button') ?>" class="<?= e($classes) ?>"<?= $disabled ? ' disabled' : '' ?><?= $confirm ? ' data-confirm="' . e($confirm) . '"' : '' ?><?= $extra ?>><?= $inner ?></button>
<?php endif; ?>
