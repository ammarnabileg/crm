<?php
/** @var string|null $workspaceName */
/** @var string $message */
?>
<div class="mx-auto max-w-lg py-16 text-center">
    <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-2xl text-amber-600">🛠</div>
    <h1 class="text-2xl font-semibold text-slate-900">Under maintenance</h1>
    <?php if ($workspaceName): ?><p class="mt-1 text-sm text-slate-400"><?= e($workspaceName) ?></p><?php endif; ?>
    <p class="mx-auto mt-4 max-w-md text-sm text-slate-600"><?= e($message) ?></p>
    <a href="/workspaces/select" class="mt-8 inline-block rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Switch workspace</a>
</div>
