<?php
/**
 * Component: Stat — a dashboard KPI tile: label, big value, optional trend (docs/30).
 * Props:
 *   - label  string  Metric name (escaped).
 *   - value  mixed   The number/figure (escaped).
 *   - icon   string  Raw SVG HTML for the corner glyph (already-safe).
 *   - delta  string  Change text, e.g. "+12%" (escaped).
 *   - trend  string  up|down|flat — colours the delta (default flat).
 *   - href   string  Optional link wrapping the tile.
 *   - class  string  Extra classes appended.
 * States: with/without trend; dark mode; hover when linked.
 * Usage:  <?= component('stat', ['label' => 'Open jobs', 'value' => 12, 'delta' => '+3', 'trend' => 'up']) ?>
 */
$trendColors = ['up' => 'text-success-600 dark:text-success-400', 'down' => 'text-red-600 dark:text-red-400', 'flat' => 'text-slate-500 dark:text-slate-400'];
$trendCls = $trendColors[$trend ?? 'flat'] ?? $trendColors['flat'];
$tag = ! empty($href) ? 'a' : 'div';
?>
<<?= $tag ?> <?php if (! empty($href)): ?>href="<?= e($href) ?>"<?php endif; ?> class="card block p-5 <?= ! empty($href) ? 'transition hover:ring-brand-300 dark:hover:ring-brand-700' : '' ?> <?= e($class ?? '') ?>">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-sm font-medium text-slate-500 dark:text-slate-400"><?= e($label ?? '') ?></p>
            <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white"><?= e((string) ($value ?? '0')) ?></p>
        </div>
        <?php if (! empty($icon)): ?>
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300"><?= $icon ?></span>
        <?php endif; ?>
    </div>
    <?php if (! empty($delta)): ?>
        <p class="mt-2 text-xs font-medium <?= e($trendCls) ?>"><?= e($delta) ?></p>
    <?php endif; ?>
</<?= $tag ?>>
