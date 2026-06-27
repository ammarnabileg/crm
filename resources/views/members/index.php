<?php
/** @var list<array<string,mixed>> $members */
/** @var list<array<string,mixed>> $roles */
/** @var bool $canInvite */
/** @var string|null $code */
/** @var string|null $status */
/** @var string|null $error */
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Members</h1>
        <p class="mt-1 text-sm text-slate-500"><?= count($members) ?> member(s) in this workspace</p>
    </div>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>
<?php if ($code): ?>
    <div class="mb-4 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-700">
        Invitation code: <span class="font-mono font-semibold"><?= e($code) ?></span>
        — share <span class="font-mono">/invitations/<?= e($code) ?></span>
    </div>
<?php endif; ?>

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
            <tr><th class="px-5 py-3">Name</th><th class="px-5 py-3">Email</th><th class="px-5 py-3">Roles</th><th class="px-5 py-3">Status</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($members as $m): ?>
                <tr>
                    <td class="px-5 py-3 font-medium text-slate-800"><?= e($m['name']) ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($m['email']) ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($m['roles'] ?? '—') ?></td>
                    <td class="px-5 py-3"><span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700"><?= e($m['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($canInvite): ?>
    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-slate-900">Invite a member</h2>
        <form method="post" action="/members/invite" class="flex flex-wrap items-end gap-3">
            <?= csrf_field() ?>
            <div class="grow">
                <label class="mb-1 block text-xs font-medium text-slate-600">Email</label>
                <input name="email" type="email" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="teammate@company.com">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Role (optional)</label>
                <select name="role_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    <option value="">No role</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= e($r['id']) ?>"><?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Send invite</button>
        </form>
    </div>
<?php endif; ?>
