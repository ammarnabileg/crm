<?php
/** @var list<array<string,mixed>> $workspaces */
/** @var string|null $currentId */
/** @var string $userId */
/** @var int $cap */
/** @var int $ownedActive */
/** @var array{total:int,active:int,suspended:int} $stats */
/** @var string|null $status */
/** @var string|null $error */
$statusStyles = [
    'active' => 'bg-emerald-50 text-emerald-700',
    'archived' => 'bg-amber-50 text-amber-700',
    'suspended' => 'bg-rose-50 text-rose-700',
];
$atCap = $ownedActive >= $cap;
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">My Workspaces</h1>
    <p class="mt-1 text-sm text-slate-500">Every workspace you belong to — in any role. Owned workspaces can be activated or paused within your plan.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Total</div><div class="mt-1 text-2xl font-bold text-slate-900"><?= e($stats['total']) ?></div></div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Active</div><div class="mt-1 text-2xl font-bold text-emerald-700"><?= e($stats['active']) ?></div></div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between">
            <div>
                <div class="text-xs uppercase tracking-wide text-slate-400">Owned running / plan</div>
                <div class="mt-1 text-2xl font-bold <?= $atCap ? 'text-amber-600' : 'text-slate-900' ?>"><?= e($ownedActive) ?> / <?= e($cap) ?></div>
            </div>
            <a href="/account/plan" class="text-xs font-medium text-indigo-600 hover:underline">Change plan →</a>
        </div>
    </div>
</div>

<?php if ($atCap): ?>
    <div class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700">You're using all <?= e($cap) ?> workspace slot(s) on your plan. Deactivate one to activate another, or upgrade your plan.</div>
<?php endif; ?>

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
            <tr><th class="px-5 py-3">Workspace</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Owner</th><th class="px-5 py-3">Members</th><th class="px-5 py-3">Plan</th><th class="px-5 py-3">Created</th><th class="px-5 py-3 text-right">Actions</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($workspaces as $w): ?>
                <?php
                $isCurrent = (string) $w['id'] === (string) $currentId;
                $isOwner = (string) ($w['owner_user_id'] ?? '') === $userId;
                $wStatus = (string) $w['status'];
                $isActive = $wStatus === 'active';
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-medium text-slate-800">
                        <?= e($w['name']) ?>
                        <?php if ($isCurrent): ?><span class="ms-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">current</span><?php endif; ?>
                        <?php if ($isOwner): ?><span class="ms-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">owner</span><?php endif; ?>
                    </td>
                    <td class="px-5 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $statusStyles[$wStatus] ?? 'bg-slate-100 text-slate-500' ?>"><?= e($isActive ? 'active' : ($wStatus === 'archived' ? 'paused' : $wStatus)) ?></span></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($w['owner_name'] ?? '—') ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($w['members']) ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($w['plan_name'] ?? 'Free') ?></td>
                    <td class="px-5 py-3 text-xs text-slate-400"><?= e($w['created_at']) ?></td>
                    <td class="px-5 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <?php if ($isOwner && $isActive): ?>
                                <form method="post" action="/workspaces/<?= e($w['id']) ?>/deactivate" onsubmit="return confirm('Deactivate “<?= e($w['name']) ?>”? Members will see a paused screen until you re-activate it.')">
                                    <?= csrf_field() ?>
                                    <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">Deactivate</button>
                                </form>
                            <?php elseif ($isOwner && $wStatus === 'archived'): ?>
                                <?php if ($atCap): ?>
                                    <button disabled title="You're at your plan's workspace limit. Deactivate another first." class="cursor-not-allowed rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-300">Activate</button>
                                <?php else: ?>
                                    <form method="post" action="/workspaces/<?= e($w['id']) ?>/activate">
                                        <?= csrf_field() ?>
                                        <button class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Activate</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($isActive && ! $isCurrent): ?>
                                <form method="post" action="/workspaces/<?= e($w['id']) ?>/switch">
                                    <?= csrf_field() ?>
                                    <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">Enter</button>
                                </form>
                            <?php elseif ($isActive && $isCurrent): ?>
                                <a href="/dashboard" class="text-xs font-medium text-indigo-600 hover:underline">Open</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
