<?php
/** @var string|null $workspaceName */
/** @var array{email:string,url:string,phone:string,message:string} $support */
/** @var bool $canManageBilling */
?>
<div class="mx-auto max-w-lg px-6 py-16 text-center">
    <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-rose-100 text-3xl">🔒</div>
    <h1 class="text-2xl font-semibold text-slate-900">Plan paused</h1>
    <p class="mt-2 text-sm text-slate-600">
        <?php if (! empty($workspaceName)): ?><span class="font-medium text-slate-800"><?= e($workspaceName) ?></span>’s plan could not be renewed because the wallet ran out of credits.<?php else: ?>This workspace’s plan could not be renewed because the wallet ran out of credits.<?php endif; ?>
    </p>

    <?php if ($canManageBilling): ?>
        <p class="mt-3 text-sm text-slate-600">Top up the wallet and re-activate the plan to restore access for everyone.</p>
        <div class="mt-6">
            <a href="/billing" class="inline-block rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Go to billing</a>
        </div>
    <?php else: ?>
        <p class="mt-3 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">You don’t have access while the plan is paused. Please ask an administrator with billing access to top up the wallet.</p>
    <?php endif; ?>

    <p class="mt-8"><a href="/my-workspaces" class="text-xs text-slate-400 hover:text-slate-600">← My workspaces</a></p>
</div>
