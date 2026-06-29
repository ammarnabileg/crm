<?php
/** @var array<string,mixed>|null $user */
/** @var list<array<string,mixed>> $member */
/** @var list<array<string,mixed>> $candidate */
?>
<h1 class="mb-1 text-xl font-semibold text-slate-900">Choose a workspace</h1>
<p class="mb-6 text-sm text-slate-500">Select which workspace to enter.</p>

<?php if ($member === [] && $candidate === []): ?>
    <div class="mb-6 rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
        You’re not in any workspace yet. Create your own, or apply to a job to join one as a candidate.
    </div>
<?php endif; ?>

<?php if ($member !== []): ?>
    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Where you work</h2>
    <div class="mb-6 space-y-2">
        <?php foreach ($member as $w): ?>
            <form method="post" action="/workspaces/<?= e($w['id']) ?>/switch">
                <?= csrf_field() ?>
                <button class="flex w-full items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-left hover:border-indigo-300 hover:bg-indigo-50/40">
                    <span>
                        <span class="block text-sm font-medium text-slate-900"><?= e($w['name']) ?></span>
                        <span class="block text-xs text-slate-400"><?= e($w['members']) ?> member(s)<?php if (! empty($w['plan_name'])): ?> · <?= e($w['plan_name']) ?><?php endif; ?></span>
                    </span>
                    <span class="text-xs font-medium text-indigo-600">Enter →</span>
                </button>
            </form>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($candidate !== []): ?>
    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Where you’ve applied</h2>
    <div class="mb-6 space-y-2">
        <?php foreach ($candidate as $w): ?>
            <form method="post" action="/candidacy/<?= e($w['id']) ?>/switch">
                <?= csrf_field() ?>
                <button class="flex w-full items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-left hover:border-emerald-300 hover:bg-emerald-50/40">
                    <span>
                        <span class="block text-sm font-medium text-slate-900"><?= e($w['name']) ?></span>
                        <span class="block text-xs text-slate-400"><?= e($w['applications_count']) ?> application(s) · candidate</span>
                    </span>
                    <span class="text-xs font-medium text-emerald-600">Open portal →</span>
                </button>
            </form>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<a href="/workspaces/create" class="block rounded-xl border-2 border-dashed border-slate-200 px-4 py-4 text-center hover:border-indigo-300 hover:bg-indigo-50/40">
    <span class="block text-sm font-semibold text-slate-900">Create your workspace</span>
    <span class="block text-xs text-slate-400">You’ll be the owner with full control.</span>
</a>
