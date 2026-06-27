<?php
/**
 * Component: Calendar — a static month grid with prev/next navigation (server-driven
 *            links), today highlighted and optional per-day event markers. RTL-aware
 *            (docs/30). No JS — navigation is real links.
 * Props:
 *   - year   int     Year (default current).
 *   - month  int     Month 1–12 (default current).
 *   - events array   Map 'YYYY-MM-DD' => label/count (escaped) to mark days.
 *   - base   string  Base URL for prev/next (default current path); gets ?year=&month=.
 *   - class  string  Extra classes appended.
 * States: today, days with events, leading/trailing blanks; dark mode.
 * Usage:  <?= component('calendar', ['events'=>['2026-06-27'=>'Interview']]) ?>
 */
$y = (int) ($year ?? (int) date('Y'));
$m = (int) ($month ?? (int) date('n'));
if ($m < 1) { $m = 12; $y--; }
if ($m > 12) { $m = 1; $y++; }
$first = mktime(0, 0, 0, $m, 1, $y);
$daysIn = (int) date('t', $first);
$startDow = (int) date('w', $first);
$todayStr = date('Y-m-d');
$events = $events ?? [];
$base = $base ?? ('/' . ltrim(request()->path(), '/'));
$link = static fn (int $yy, int $mm): string => $base . (str_contains($base, '?') ? '&' : '?') . 'year=' . $yy . '&month=' . $mm;
$prevM = $m - 1; $prevY = $y; if ($prevM < 1) { $prevM = 12; $prevY--; }
$nextM = $m + 1; $nextY = $y; if ($nextM > 12) { $nextM = 1; $nextY++; }
$weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$cells = array_fill(0, $startDow, null);
for ($d = 1; $d <= $daysIn; $d++) { $cells[] = $d; }
while (count($cells) % 7 !== 0) { $cells[] = null; }
?>
<div class="card card-body <?= e($class ?? '') ?>">
    <div class="mb-4 flex items-center justify-between">
        <a href="<?= e($link($prevY, $prevM)) ?>" class="btn-ghost px-2 py-1.5" aria-label="Previous month">
            <svg class="h-5 w-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100"><?= e(date('F Y', $first)) ?></h3>
        <a href="<?= e($link($nextY, $nextM)) ?>" class="btn-ghost px-2 py-1.5" aria-label="Next month">
            <svg class="h-5 w-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
    <div class="grid grid-cols-7 gap-1 text-center">
        <?php foreach ($weekdays as $wd): ?>
            <div class="py-1 text-xs font-semibold uppercase text-slate-400"><?= e($wd) ?></div>
        <?php endforeach; ?>
        <?php foreach ($cells as $d): ?>
            <?php if ($d === null): ?>
                <div></div>
            <?php else: $ds = sprintf('%04d-%02d-%02d', $y, $m, $d); $isToday = $ds === $todayStr; $hasEvent = isset($events[$ds]); ?>
                <div class="relative aspect-square rounded-lg p-1 text-sm <?= $isToday ? 'bg-brand-600 font-semibold text-white' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' ?>"
                     <?= $hasEvent ? 'title="' . e((string) $events[$ds]) . '"' : '' ?>>
                    <?= e((string) $d) ?>
                    <?php if ($hasEvent): ?><span class="absolute inset-x-0 bottom-1 mx-auto h-1.5 w-1.5 rounded-full <?= $isToday ? 'bg-white' : 'bg-brand-500' ?>"></span><?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
