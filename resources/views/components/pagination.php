<?php
/**
 * Component: Pagination — windowed page navigation with prev/next, flow-relative
 *            for RTL/LTR. Server-driven (links are real URLs) (docs/30 Table System).
 * Props:
 *   - current int     Current page (1-based).
 *   - last    int     Last page number.
 *   - base    string  Base URL the page param is appended to (default current path).
 *   - param   string  Query parameter name (default "page").
 *   - class   string  Extra classes appended.
 * States: active page, disabled prev/next at the ends, ellipses, dark mode.
 * Usage:  <?= component('pagination', ['current'=>2, 'last'=>9, 'base'=>url('jobs')]) ?>
 */
$current = max(1, (int) ($current ?? 1));
$last = max(1, (int) ($last ?? 1));
$param = $param ?? 'page';
$base = $base ?? ('/' . ltrim(request()->path(), '/'));
$href = static function (int $p) use ($base, $param): string {
    return $base . (str_contains($base, '?') ? '&' : '?') . $param . '=' . $p;
};
// Build a compact window: 1 … current-1 current current+1 … last
$pages = [];
foreach (range(1, $last) as $p) {
    if ($p === 1 || $p === $last || abs($p - $current) <= 1) {
        $pages[] = $p;
    } elseif (end($pages) !== '…') {
        $pages[] = '…';
    }
}
$itemBase = 'inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-3 text-sm font-medium';
if ($last <= 1) {
    return;
}
?>
<nav class="flex items-center gap-1 <?= e($class ?? '') ?>" aria-label="Pagination">
    <?php if ($current > 1): ?>
        <a href="<?= e($href($current - 1)) ?>" rel="prev" class="<?= e($itemBase) ?> text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" aria-label="Previous">
            <svg class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
    <?php else: ?>
        <span class="<?= e($itemBase) ?> cursor-not-allowed text-slate-300 dark:text-slate-700" aria-disabled="true">
            <svg class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </span>
    <?php endif; ?>

    <?php foreach ($pages as $p): ?>
        <?php if ($p === '…'): ?>
            <span class="<?= e($itemBase) ?> text-slate-400">…</span>
        <?php elseif ($p === $current): ?>
            <span class="<?= e($itemBase) ?> bg-brand-600 text-white" aria-current="page"><?= e((string) $p) ?></span>
        <?php else: ?>
            <a href="<?= e($href((int) $p)) ?>" class="<?= e($itemBase) ?> text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"><?= e((string) $p) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($current < $last): ?>
        <a href="<?= e($href($current + 1)) ?>" rel="next" class="<?= e($itemBase) ?> text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" aria-label="Next">
            <svg class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    <?php else: ?>
        <span class="<?= e($itemBase) ?> cursor-not-allowed text-slate-300 dark:text-slate-700" aria-disabled="true">
            <svg class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </span>
    <?php endif; ?>
</nav>
