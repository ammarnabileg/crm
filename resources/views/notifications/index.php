<?php
/** @var list<array<string,mixed>> $notifications */
/** @var int $unread */
/** @var array{all:int,unread:int,archived:int} $counts */
/** @var list<string> $categories */
/** @var array{state:string,category:string,q:string} $filters */
/** @var string|null $status */

$state = $filters['state'];
$qs = static function (array $overrides) use ($filters): string {
    $params = array_filter(array_merge(['state' => $filters['state'], 'category' => $filters['category'], 'q' => $filters['q']], $overrides), static fn ($v): bool => (string) $v !== '');

    return $params === [] ? '/notifications' : '/notifications?' . http_build_query($params);
};
$returnTo = $qs([]);
$tabs = ['all' => 'All', 'unread' => 'Unread', 'archived' => 'Archived'];
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

<!-- Tabs + filters -->
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 text-sm shadow-sm">
        <?php foreach ($tabs as $key => $label): ?>
            <?php $active = $state === $key; $badge = $counts[$key] ?? 0; ?>
            <a href="<?= e($qs(['state' => $key])) ?>" class="rounded-md px-3 py-1.5 font-medium <?= $active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50' ?>">
                <?= e($label) ?><?php if ($badge > 0): ?> <span class="ms-1 rounded-full <?= $active ? 'bg-white/20' : 'bg-slate-100' ?> px-1.5 text-xs"><?= (int) $badge ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="get" action="/notifications" class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="state" value="<?= e($state) ?>">
        <?php if ($categories !== []): ?>
            <select name="category" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                <option value="">All categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= $filters['category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <input name="q" value="<?= e($filters['q']) ?>" placeholder="Search…" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        <button class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Search</button>
        <?php if ($filters['category'] !== '' || $filters['q'] !== ''): ?><a href="<?= e($qs(['category' => '', 'q' => ''])) ?>" class="text-sm text-slate-500 hover:text-slate-700">Clear</a><?php endif; ?>
    </form>
</div>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <?php if ($notifications === []): ?>
        <p class="px-5 py-6 text-sm text-slate-400"><?= $state === 'archived' ? 'No archived notifications.' : ($filters['q'] !== '' || $filters['category'] !== '' ? 'No notifications match your filters.' : 'No notifications yet.') ?></p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($notifications as $n): ?>
                <?php $archived = ! empty($n['archived_at']); ?>
                <li class="flex items-start justify-between px-5 py-3 text-sm <?= empty($n['read_at']) && ! $archived ? 'bg-indigo-50/40' : '' ?>">
                    <div class="min-w-0">
                        <div class="font-medium text-slate-800">
                            <?php if (empty($n['read_at']) && ! $archived): ?><span class="me-1 inline-block h-2 w-2 rounded-full bg-indigo-500 align-middle"></span><?php endif; ?>
                            <?= e($n['title']) ?>
                        </div>
                        <?php if (! empty($n['body'])): ?><div class="text-slate-500"><?= e($n['body']) ?></div><?php endif; ?>
                        <div class="mt-0.5 text-xs text-slate-400">
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-500"><?= e($n['type']) ?></span>
                            · <?= e($n['created_at']) ?> UTC
                            <?php if (! empty($n['link'])): ?> · <a href="<?= e($n['link']) ?>" class="text-indigo-600 hover:underline">open</a><?php endif; ?>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-3 ps-3">
                        <?php if (! $archived && empty($n['read_at'])): ?>
                            <form method="post" action="/notifications/<?= e($n['id']) ?>/read"><?= csrf_field() ?><button class="text-xs font-medium text-slate-500 hover:text-slate-700">Mark read</button></form>
                        <?php endif; ?>
                        <?php if ($archived): ?>
                            <form method="post" action="/notifications/<?= e($n['id']) ?>/unarchive"><?= csrf_field() ?><input type="hidden" name="return_to" value="<?= e($returnTo) ?>"><button class="text-xs font-medium text-indigo-600 hover:text-indigo-700">Restore</button></form>
                        <?php else: ?>
                            <form method="post" action="/notifications/<?= e($n['id']) ?>/archive"><?= csrf_field() ?><input type="hidden" name="return_to" value="<?= e($returnTo) ?>"><button class="text-xs font-medium text-slate-400 hover:text-slate-600">Archive</button></form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
