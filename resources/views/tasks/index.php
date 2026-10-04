<?php
/** @var list<array<string,mixed>> $tasks */
/** @var list<array<string,mixed>> $members */
/** @var array{status:string,assignee:string,q:string} $filters */
/** @var string $currentUserId */
/** @var bool $canManage */
/** @var string|null $status */
$prioStyle = ['high' => 'bg-rose-50 text-rose-700', 'normal' => 'bg-slate-100 text-slate-500', 'low' => 'bg-slate-100 text-slate-400'];
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Tasks</h1>
    <p class="mt-1 text-sm text-slate-500">Hiring to-dos for this workspace.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-3">
        <!-- Filters -->
        <form method="get" action="/tasks" class="flex flex-wrap items-end gap-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Status</label>
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <?php foreach (['' => 'All', 'open' => 'Open', 'done' => 'Done'] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Assignee</label>
                <select name="assignee" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="" <?= $filters['assignee'] === '' ? 'selected' : '' ?>>Anyone</option>
                    <option value="me" <?= $filters['assignee'] === 'me' ? 'selected' : '' ?>>Me</option>
                </select>
            </div>
            <div class="grow">
                <label class="mb-1 block text-xs font-medium text-slate-600">Search</label>
                <input name="q" value="<?= e($filters['q']) ?>" placeholder="Title or details…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Filter</button>
        </form>

        <?php if ($tasks === []): ?>
            <div class="rounded-2xl border border-slate-200 bg-white px-5 py-10 text-center text-sm text-slate-400 shadow-sm">No tasks match.</div>
        <?php else: ?>
            <?php foreach ($tasks as $t): ?>
                <?php $done = (string) $t['status'] === 'done'; ?>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm <?= $done ? 'opacity-60' : '' ?>">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-slate-800 <?= $done ? 'line-through' : '' ?>"><?= e($t['title']) ?></span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $prioStyle[(string) ($t['priority'] ?? 'normal')] ?? 'bg-slate-100 text-slate-500' ?>"><?= e($t['priority'] ?? 'normal') ?></span>
                            </div>
                            <?php if (! empty($t['description'])): ?><p class="mt-1 text-sm text-slate-500"><?= e($t['description']) ?></p><?php endif; ?>
                            <div class="mt-1 text-xs text-slate-400">
                                <?= ! empty($t['assignee_name']) ? 'Assigned to ' . e($t['assignee_name']) : 'Unassigned' ?>
                                <?php if (! empty($t['due_at'])): ?> · due <?= e(substr((string) $t['due_at'], 0, 16)) ?><?php endif; ?>
                            </div>
                        </div>
                        <?php if ($canManage): ?>
                            <div class="flex shrink-0 items-center gap-3">
                                <?php if ($done): ?>
                                    <form method="post" action="/tasks/<?= e($t['id']) ?>/reopen"><?= csrf_field() ?><button class="text-xs font-medium text-slate-500 hover:text-slate-700">Reopen</button></form>
                                <?php else: ?>
                                    <form method="post" action="/tasks/<?= e($t['id']) ?>/complete"><?= csrf_field() ?><button class="text-xs font-medium text-emerald-600 hover:text-emerald-700">Complete</button></form>
                                <?php endif; ?>
                                <form method="post" action="/tasks/<?= e($t['id']) ?>/delete" onsubmit="return confirm('Delete this task?')"><?= csrf_field() ?><button class="text-xs font-medium text-rose-600 hover:text-rose-700">Delete</button></form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm self-start">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">New task</h2>
            <form method="post" action="/tasks" class="space-y-2">
                <?= csrf_field() ?>
                <input name="title" required placeholder="What needs doing?" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <textarea name="description" rows="2" placeholder="Details (optional)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                <select name="assignee_user_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Unassigned</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= e($m['user_id']) ?>" <?= (string) $m['user_id'] === $currentUserId ? 'selected' : '' ?>><?= e($m['name']) ?><?= (string) $m['user_id'] === $currentUserId ? ' (me)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="flex gap-2">
                    <select name="priority" class="w-1/2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <?php foreach (['normal' => 'Normal', 'high' => 'High', 'low' => 'Low'] as $v => $l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
                    </select>
                    <input name="due_at" type="date" class="w-1/2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add task</button>
            </form>
        </div>
    <?php endif; ?>
</div>
