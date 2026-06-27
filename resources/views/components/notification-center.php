<?php
/**
 * Component: Notification center — a bell trigger + dropdown panel listing recent
 *            notifications, built on native <details> (app.js closes on outside-click).
 *            Data is passed in by the caller; this is the reusable UI shell (docs/30
 *            Notifications). Wiring to a live notifications feed is the page's job.
 * Props:
 *   - items  array  Each: ['title'=>string, 'time'=>?string, 'read'=>?bool, 'href'=>?string, 'icon'=>?rawHTML].
 *   - count  int    Unread badge count (default derived from unread items).
 *   - viewAllHref string  "View all" link target.
 *   - markAllAction string URL for a POST "mark all read" form (CSRF added).
 *   - class  string Extra classes on the wrapper.
 * States: unread badge, read/unread item, empty, dark mode.
 * Usage:  <?= component('notification-center', ['items'=>[['title'=>'New application','time'=>'2m','read'=>false]]]) ?>
 */
$items = $items ?? [];
$unread = $count ?? count(array_filter($items, static fn ($i) => empty($i['read'])));
?>
<details class="relative <?= e($class ?? '') ?>" data-popover>
    <summary class="relative flex cursor-pointer list-none items-center rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Notifications">
        <svg class="h-5 w-5 text-slate-600 dark:text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        <?php if ($unread > 0): ?>
            <span class="absolute end-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white"><?= e((string) ($unread > 9 ? '9+' : $unread)) ?></span>
        <?php endif; ?>
    </summary>
    <div data-flyout class="absolute end-0 z-dropdown mt-2 w-80 max-w-[90vw] overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
            <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">Notifications</span>
            <?php if (! empty($markAllAction)): ?>
                <form method="POST" action="<?= e($markAllAction) ?>"><?= csrf_field() ?><button class="text-xs text-brand-600 hover:underline dark:text-brand-300">Mark all read</button></form>
            <?php endif; ?>
        </div>
        <div class="max-h-80 overflow-y-auto">
            <?php if ($items === []): ?>
                <p class="px-4 py-8 text-center text-sm text-slate-400">You're all caught up.</p>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <a href="<?= e($item['href'] ?? '#') ?>" class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800 <?= empty($item['read']) ? 'bg-brand-50/50 dark:bg-brand-900/20' : '' ?>">
                        <?php if (! empty($item['icon'])): ?><span class="mt-0.5 shrink-0 text-slate-400"><?= $item['icon'] ?></span><?php endif; ?>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm <?= empty($item['read']) ? 'font-semibold text-slate-800 dark:text-slate-100' : 'text-slate-600 dark:text-slate-300' ?>"><?= e($item['title'] ?? '') ?></span>
                            <?php if (! empty($item['time'])): ?><span class="text-xs text-slate-400"><?= e($item['time']) ?></span><?php endif; ?>
                        </span>
                        <?php if (empty($item['read'])): ?><span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-brand-500"></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if (! empty($viewAllHref)): ?>
            <a href="<?= e($viewAllHref) ?>" class="block border-t border-slate-100 px-4 py-3 text-center text-sm font-medium text-brand-600 hover:bg-slate-50 dark:border-slate-800 dark:text-brand-300 dark:hover:bg-slate-800">View all</a>
        <?php endif; ?>
    </div>
</details>
