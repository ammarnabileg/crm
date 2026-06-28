<?php
/** @var list<array<string,mixed>> $notifications */
/** @var int $unread */
/** @var string|null $status */
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Notifications</h1>
        <p class="mt-1 text-sm text-slate-500"><?= e($unread) ?> unread in this workspace.</p>
    </div>
    <?php if ($unread > 0): ?>
        <form method="post" action="/notifications/read">
            <?= csrf_field() ?>
            <button class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">Mark all read</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <?php if ($notifications === []): ?>
        <p class="px-5 py-6 text-sm text-slate-400">No notifications yet.</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($notifications as $n): ?>
                <li class="flex items-start justify-between px-5 py-3 text-sm <?= empty($n['read_at']) ? 'bg-indigo-50/40' : '' ?>">
                    <div class="min-w-0">
                        <div class="font-medium text-slate-800">
                            <?php if (empty($n['read_at'])): ?><span class="me-1 inline-block h-2 w-2 rounded-full bg-indigo-500 align-middle"></span><?php endif; ?>
                            <?= e($n['title']) ?>
                        </div>
                        <?php if (! empty($n['body'])): ?><div class="text-slate-500"><?= e($n['body']) ?></div><?php endif; ?>
                        <div class="mt-0.5 text-xs text-slate-400"><?= e($n['type']) ?> · <?= e($n['created_at']) ?> UTC
                            <?php if (! empty($n['link'])): ?> · <a href="<?= e($n['link']) ?>" class="text-indigo-600 hover:underline">open</a><?php endif; ?>
                        </div>
                    </div>
                    <?php if (empty($n['read_at'])): ?>
                        <form method="post" action="/notifications/<?= e($n['id']) ?>/read">
                            <?= csrf_field() ?>
                            <button class="text-xs font-medium text-slate-500 hover:text-slate-700">Mark read</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
