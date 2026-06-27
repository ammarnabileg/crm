<?php
/**
 * Component: Table — the unified data table: horizontally scrollable on small
 *            screens, optional sticky header, zebra hover, dark mode (docs/30).
 * Props:
 *   - columns array  Each: string label OR ['label'=>, 'key'=>?, 'align'=>start|center|end, 'class'=>].
 *   - rows    array  Each row: a list of raw-HTML cells (already-safe) OR an
 *                    associative array read by each column's 'key' (escaped).
 *   - empty   string Text shown (centered) when there are no rows (escaped).
 *   - sticky  bool   Stick the header on vertical scroll (default false).
 *   - class   string Extra classes appended to the wrapper.
 * States: with rows, empty, sticky header, dark mode.
 * Usage:  <?= component('table', ['columns'=>['Name','Status'], 'rows'=>[['Sara', $badge]]]) ?>
 */
$columns = $columns ?? [];
$rows = $rows ?? [];
$aligns = ['start' => 'text-start', 'center' => 'text-center', 'end' => 'text-end'];
$colCount = count($columns);
?>
<div class="overflow-x-auto rounded-xl ring-1 ring-slate-200 dark:ring-slate-800 <?= e($class ?? '') ?>">
    <table class="table-base <?= ($sticky ?? false) ? 'table-sticky' : '' ?>">
        <thead>
            <tr>
                <?php foreach ($columns as $col): ?>
                    <?php $label = is_array($col) ? ($col['label'] ?? '') : $col; $align = is_array($col) ? ($aligns[$col['align'] ?? 'start'] ?? 'text-start') : 'text-start'; ?>
                    <th class="<?= e($align) ?> <?= e(is_array($col) ? ($col['class'] ?? '') : '') ?>"><?= e($label) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="<?= e((string) max(1, $colCount)) ?>" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400"><?= e($empty ?? 'No data to display.') ?></td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php if (array_is_list($row)): ?>
                            <?php foreach ($row as $cell): ?><td><?= $cell ?></td><?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($columns as $col): ?>
                                <?php $key = is_array($col) ? ($col['key'] ?? null) : null; $align = is_array($col) ? ($aligns[$col['align'] ?? 'start'] ?? 'text-start') : 'text-start'; ?>
                                <td class="<?= e($align) ?>"><?= $key !== null ? e($row[$key] ?? '') : '' ?></td>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
