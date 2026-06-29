<?php
/** @var list<array<string,mixed>> $invites */
/** @var string|null $status */
/** @var string|null $error */
?>
<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Your invitations</h1>
        <p class="mt-1 text-sm text-slate-500">Workspaces that have invited you. You join only when you accept.</p>
    </div>

    <?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

    <div class="space-y-3">
        <?php foreach ($invites as $inv): ?>
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-100 text-base font-bold text-indigo-700"><?= e(strtoupper(substr((string) $inv['workspace_name'], 0, 1))) ?></span>
                    <div>
                        <div class="font-semibold text-slate-900"><?= e($inv['workspace_name']) ?></div>
                        <div class="text-xs text-slate-400">
                            <?php if (! empty($inv['inviter_name'])): ?>Invited by <?= e($inv['inviter_name']) ?> · <?php endif; ?>
                            <?= e(count($inv['role_ids'])) ?> role(s)
                            <?php if (! empty($inv['expires_at'])): ?> · expires <?= e(substr((string) $inv['expires_at'], 0, 10)) ?><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <form method="post" action="/invites/<?= e($inv['id']) ?>/accept">
                        <?= csrf_field() ?>
                        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Accept</button>
                    </form>
                    <form method="post" action="/invites/<?= e($inv['id']) ?>/decline" onsubmit="return confirm('Decline the invitation from “<?= e($inv['workspace_name']) ?>”?')">
                        <?= csrf_field() ?>
                        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Decline</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($invites === []): ?>
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.4" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                <p class="mt-3 text-sm font-medium text-slate-600">No pending invitations.</p>
                <p class="mt-1 text-sm text-slate-400">When a workspace invites you, it'll appear here to accept.</p>
                <a href="/dashboard" class="mt-4 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Go to dashboard</a>
            </div>
        <?php endif; ?>
    </div>
</div>
