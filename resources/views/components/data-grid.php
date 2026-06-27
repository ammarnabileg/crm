<?php
/**
 * Component: DataGrid — the Table System's power variant: a toolbar (search/filters),
 *            server-driven sortable column headers, optional row selection, and a
 *            footer (pagination). Sorting/filtering happen on the server via real
 *            links/forms (works without JS); select-all is enhancement (docs/30).
 * Props:
 *   - columns array  Each: ['label'=>, 'key'=>?, 'sort'=>?sortKey, 'align'=>start|center|end].
 *   - rows    array  Each row: list of raw-HTML cells OR associative (read by 'key', escaped).
 *   - sort    string Current sort key (matches a column's 'sort').
 *   - dir     string asc|desc (default asc).
 *   - base    string Base URL sort links append to (default current path).
 *   - toolbar string Raw HTML toolbar (search + actions) (already-safe).
 *   - footer  string Raw HTML footer, e.g. component('pagination', …) (already-safe).
 *   - empty   string Text when there are no rows (escaped).
 *   - selectable bool Render a select column with a select-all checkbox (default false).
 *   - class   string Extra classes appended to the wrapper.
 * States: sorted column (aria-sort + arrow), empty, selectable, dark mode.
 * Usage:  <?= component('data-grid', ['columns'=>[['label'=>'Name','sort'=>'name']], 'rows'=>$rows, 'sort'=>'name', 'dir'=>'asc']) ?>
 */
$columns = $columns ?? [];
$rows = $rows ?? [];
$sort = $sort ?? null;
$dir = ($dir ?? 'asc') === 'desc' ? 'desc' : 'asc';
$base = $base ?? ('/' . ltrim(request()->path(), '/'));
$aligns = ['start' => 'text-start', 'center' => 'text-center', 'end' => 'text-end'];
$colSpan = count($columns) + (($selectable ?? false) ? 1 : 0);
$sortLink = static function (string $key) use ($base, $sort, $dir): string {
    $next = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    return $base . (str_contains($base, '?') ? '&' : '?') . 'sort=' . rawurlencode($key) . '&dir=' . $next;
};
?>
<div class="min-w-0 space-y-3 <?= e($class ?? '') ?>">
    <?php if (! empty($toolbar)): ?>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><?= $toolbar ?></div>
    <?php endif; ?>
    <div class="overflow-x-auto rounded-xl ring-1 ring-slate-200 dark:ring-slate-800">
        <table class="table-base">
            <thead>
                <tr>
                    <?php if ($selectable ?? false): ?>
                        <th class="w-10"><input type="checkbox" data-grid-select-all aria-label="Select all" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800"></th>
                    <?php endif; ?>
                    <?php foreach ($columns as $col): ?>
                        <?php
                        $label = $col['label'] ?? '';
                        $align = $aligns[$col['align'] ?? 'start'] ?? 'text-start';
                        $sk = $col['sort'] ?? null;
                        $active = $sk !== null && $sort === $sk;
                        ?>
                        <th class="<?= e($align) ?>" <?= $active ? 'aria-sort="' . ($dir === 'asc' ? 'ascending' : 'descending') . '"' : '' ?>>
                            <?php if ($sk !== null): ?>
                                <a href="<?= e($sortLink($sk)) ?>" class="inline-flex items-center gap-1 hover:text-slate-700 dark:hover:text-slate-200">
                                    <?= e($label) ?>
                                    <span class="text-slate-400">
                                        <?php if ($active && $dir === 'asc'): ?>&#9650;<?php elseif ($active): ?>&#9660;<?php else: ?>&#8693;<?php endif; ?>
                                    </span>
                                </a>
                            <?php else: ?><?= e($label) ?><?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="<?= e((string) max(1, $colSpan)) ?>" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400"><?= e($empty ?? 'No data to display.') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php if ($selectable ?? false): ?>
                                <td><input type="checkbox" data-grid-select aria-label="Select row" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800"></td>
                            <?php endif; ?>
                            <?php if (array_is_list($row)): ?>
                                <?php foreach ($row as $cell): ?><td><?= $cell ?></td><?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($columns as $col): $key = $col['key'] ?? null; $align = $aligns[$col['align'] ?? 'start'] ?? 'text-start'; ?>
                                    <td class="<?= e($align) ?>"><?= $key !== null ? e($row[$key] ?? '') : '' ?></td>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (! empty($footer)): ?><div class="flex justify-end"><?= $footer ?></div><?php endif; ?>
</div>
