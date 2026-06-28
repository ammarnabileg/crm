<?php
/** @var list<array<string,mixed>> $users */
/** @var list<array<string,mixed>> $plans */
/** @var string $q */
/** @var string $currentUserId */
/** @var string|null $status */
$statusStyles = [
    'active' => 'bg-emerald-50 text-emerald-700',
    'suspended' => 'bg-amber-50 text-amber-700',
    'deactivated' => 'bg-rose-50 text-rose-700',
];
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Users</h1>
        <p class="mt-1 text-sm text-slate-500">Every user across all workspaces — one identity, many contexts.</p>
    </div>
    <form method="get" action="/admin/users" class="flex items-center gap-2">
        <input name="q" value="<?= e($q) ?>" placeholder="Search name or email…" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        <button class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Search</button>
        <?php if ($q !== ''): ?><a href="/admin/users" class="text-sm text-slate-500 hover:text-slate-700">Clear</a><?php endif; ?>
    </form>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<p class="mb-3 text-xs text-slate-400"><?= count($users) ?> user(s)<?= $q !== '' ? ' matching "' . e($q) . '"' : '' ?>.</p>

<div class="space-y-3">
    <?php foreach ($users as $u): ?>
        <?php
        $st = (string) ($u['status'] ?? 'active');
        $isOwner = (int) $u['is_system_owner'] === 1;
        $isSelf = (string) $u['id'] === $currentUserId;
        $canCreate = (int) ($u['can_create_workspaces'] ?? 1) === 1;
        $limits = is_array($u['plan_limits'] ?? null) ? $u['plan_limits'] : (is_string($u['plan_limits'] ?? null) ? (json_decode((string) $u['plan_limits'], true) ?: []) : []);
        $cap = (int) ($limits['workspaces'] ?? 1);
        ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-medium text-slate-800">
                        <?= e($u['name']) ?>
                        <?php if ($isOwner): ?><span class="ml-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">System Owner</span><?php endif; ?>
                        <?php if ($isSelf): ?><span class="ml-1 text-xs text-slate-400">(you)</span><?php endif; ?>
                        <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-medium <?= $statusStyles[$st] ?? 'bg-slate-100 text-slate-500' ?>"><?= e($st) ?></span>
                    </div>
                    <div class="text-xs text-slate-400"><?= e($u['email']) ?> · <?= e($u['workspaces']) ?> membership(s) · last login <?= e(time_ago($u['last_login_at'] ?? null)) ?></div>
                    <div class="mt-1 text-xs text-slate-500">
                        Plan: <span class="font-medium text-slate-700"><?= e($u['plan_name'] ?? 'Free') ?></span>
                        · workspaces <?= (int) ($u['owned_active'] ?? 0) ?>/<?= $cap ?>
                        <?php if (! empty($u['expires_at'])): ?> · renews <?= e(substr((string) $u['expires_at'], 0, 10)) ?><?php endif; ?>
                        · creation <?= $canCreate ? '<span class="text-emerald-600">allowed</span>' : '<span class="text-rose-600">blocked</span>' ?>
                    </div>
                </div>
                <?php if (! $isOwner && ! $isSelf): ?>
                    <div class="flex shrink-0 items-center gap-2">
                        <?php if ($st === 'active'): ?>
                            <form method="post" action="/admin/users/<?= e($u['id']) ?>/deactivate" onsubmit="return confirm('Deactivate <?= e($u['name']) ?>?')"><?= csrf_field() ?><button class="text-xs font-medium text-rose-600 hover:text-rose-700">Deactivate</button></form>
                        <?php else: ?>
                            <form method="post" action="/admin/users/<?= e($u['id']) ?>/activate"><?= csrf_field() ?><button class="text-xs font-medium text-emerald-600 hover:text-emerald-700">Activate</button></form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (! $isOwner && ! $isSelf): ?>
                <div class="mt-3 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-3">
                    <form method="post" action="/admin/users/<?= e($u['id']) ?>/workspace-creation">
                        <?= csrf_field() ?>
                        <input type="hidden" name="allow" value="<?= $canCreate ? '0' : '1' ?>">
                        <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"><?= $canCreate ? 'Block workspace creation' : 'Allow workspace creation' ?></button>
                    </form>
                    <form method="post" action="/admin/users/<?= e($u['id']) ?>/plan" class="flex items-end gap-1">
                        <?= csrf_field() ?>
                        <select name="plan_id" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                            <option value="">Free (1 workspace)</option>
                            <?php foreach ($plans as $pl): ?>
                                <?php $pcap = (int) (((is_array($pl['limits'] ?? null) ? $pl['limits'] : []))['workspaces'] ?? 1); ?>
                                <option value="<?= e($pl['id']) ?>" <?= (string) ($u['plan_id'] ?? '') === (string) $pl['id'] ? 'selected' : '' ?>><?= e($pl['name']) ?> (<?= $pcap ?> ws)</option>
                            <?php endforeach; ?>
                        </select>
                        <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Assign plan</button>
                    </form>
                    <form method="post" action="/admin/users/<?= e($u['id']) ?>/grant-months" class="flex items-end gap-1">
                        <?= csrf_field() ?>
                        <input name="months" type="number" min="1" max="120" value="1" class="w-16 rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                        <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Grant free months</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if ($users === []): ?><div class="rounded-2xl border border-slate-200 bg-white px-5 py-8 text-center text-sm text-slate-400 shadow-sm">No users<?= $q !== '' ? ' match your search' : '' ?>.</div><?php endif; ?>
</div>
