<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * The signed-in user's notification feed. Unread rows are emphasised; each unread
 * row offers an inline "Mark read", and the header offers "Mark all read".
 *
 * @var array<int,array<string,mixed>> $notifications  Rows from the notifications table.
 * @var int $unreadCount  Unread notifications for the current user.
 * @var int $page         Current page (1-based).
 * @var int $lastPage     Last page number.
 */
$actions = '';
if ($unreadCount > 0) {
    $actions = '<form method="POST" action="' . e(url('notifications/read-all')) . '">'
        . csrf_field()
        . component('button', ['label' => 'Mark all read', 'variant' => 'secondary', 'size' => 'sm', 'type' => 'submit'])
        . '</form>';
}
$subtitle = $unreadCount > 0
    ? ($unreadCount === 1 ? '1 unread notification' : $unreadCount . ' unread notifications')
    : 'You are all caught up';
?>
<?= component('page-header', ['title' => 'Notifications', 'subtitle' => $subtitle, 'actions' => $actions]) ?>

<?php if ($notifications === []): ?>
    <?= component('state', [
        'variant' => 'empty',
        'title'   => 'No notifications yet',
        'message' => 'When something needs your attention, it will show up here.',
    ]) ?>
<?php else: ?>
    <div class="card">
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($notifications as $n): ?>
                <?php $isUnread = ($n['read_at'] ?? null) === null; ?>
                <li class="flex items-start gap-3 px-4 py-4 sm:px-6 <?= $isUnread ? 'bg-brand-50/50 dark:bg-brand-900/20' : '' ?>">
                    <?php if ($isUnread): ?>
                        <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-brand-500" aria-hidden="true"></span>
                    <?php else: ?>
                        <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-transparent" aria-hidden="true"></span>
                    <?php endif; ?>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm <?= $isUnread ? 'font-semibold text-slate-900 dark:text-slate-100' : 'text-slate-700 dark:text-slate-300' ?>">
                                <?= e((string) ($n['title'] ?? '')) ?>
                            </p>
                            <div class="flex shrink-0 items-center gap-3">
                                <time class="text-xs text-slate-400"><?= e((string) ($n['created_at'] ?? '')) ?></time>
                                <?php if ($isUnread): ?>
                                    <form method="POST" action="<?= e(url('notifications/read')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) ($n['id'] ?? '')) ?>">
                                        <button type="submit" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-300">Mark read</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (! empty($n['body'])): ?>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= e((string) $n['body']) ?></p>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if ($lastPage > 1): ?>
        <div class="mt-6 flex justify-center">
            <?= component('pagination', ['current' => $page, 'last' => $lastPage, 'base' => url('notifications')]) ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php $this->endSection(); ?>
