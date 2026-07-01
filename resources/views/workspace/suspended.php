<?php
/** @var string|null $workspaceName */
/** @var array{email:string,url:string,phone:string,message:string} $support */
/** @var string|null $reason */
$contactUrl = $support['url'] !== '' ? $support['url'] : ($support['email'] !== '' ? 'mailto:' . $support['email'] : '');
$reasons = [
    'suspended' => 'has been stopped by the platform.',
    'archived' => 'has been paused by its owner.',
    'plan' => 'is unavailable because the subscription has not been renewed.',
];
$lead = $reasons[$reason ?? ''] ?? 'is currently unavailable.';
?>
<div class="mx-auto max-w-lg px-6 py-16 text-center">
    <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-3xl">⏸</div>
    <h1 class="text-2xl font-semibold text-slate-900">Service paused</h1>
    <p class="mt-2 text-sm text-slate-600">
        <?php if (! empty($workspaceName)): ?><span class="font-medium text-slate-800"><?= e($workspaceName) ?></span> <?= e($lead) ?><?php else: ?>This workspace <?= e($lead) ?><?php endif; ?>
    </p>
    <?php if ($support['message'] !== ''): ?>
        <p class="mt-3 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600"><?= nl2br(e($support['message'])) ?></p>
    <?php endif; ?>

    <div class="mt-6 space-y-2">
        <?php if ($contactUrl !== ''): ?>
            <a href="<?= e($contactUrl) ?>" class="inline-block rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Contact support</a>
        <?php endif; ?>
        <div class="text-xs text-slate-400">
            <?php if ($support['email'] !== ''): ?>Email: <a href="mailto:<?= e($support['email']) ?>" class="text-indigo-600 hover:underline"><?= e($support['email']) ?></a><?php endif; ?>
            <?php if ($support['phone'] !== ''): ?> · Phone: <?= e($support['phone']) ?><?php endif; ?>
        </div>
        <?php if ($contactUrl === '' && $support['email'] === ''): ?>
            <p class="text-xs text-slate-400">Please contact your platform administrator.</p>
        <?php endif; ?>
    </div>

    <p class="mt-8"><a href="/my-workspaces" class="text-xs text-slate-400 hover:text-slate-600">← My workspaces</a></p>
</div>
