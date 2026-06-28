<?php
/** @var string $state */
/** @var string $token */
// state: valid | expired | invalid | completed | revoked | done
?>
<div class="mx-auto mt-16 max-w-lg rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
    <div class="mb-2 text-xl font-bold tracking-tight text-slate-900">HaHire<span class="text-indigo-600">AI</span></div>
    <?php if ($state === 'valid'): ?>
        <h1 class="mt-4 text-2xl font-semibold text-slate-900">Your AI interview is ready</h1>
        <p class="mt-2 text-sm text-slate-500">This link is valid and single-use. When you're ready, start the interview below.</p>
        <form method="post" action="/interview/<?= e($token) ?>/start" class="mt-6">
            <?= csrf_field() ?>
            <button class="rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-700">Start interview</button>
        </form>
    <?php elseif ($state === 'done'): ?>
        <h1 class="mt-4 text-2xl font-semibold text-emerald-700">Interview completed successfully</h1>
        <p class="mt-2 text-sm text-slate-500">Thank you. Your responses have been recorded and shared with the hiring team. You can close this page.</p>
    <?php elseif ($state === 'completed'): ?>
        <h1 class="mt-4 text-2xl font-semibold text-slate-900">Interview completed successfully</h1>
        <p class="mt-2 text-sm text-slate-500">This interview has already been completed. The link can't be used again.</p>
    <?php else: ?>
        <h1 class="mt-4 text-2xl font-semibold text-rose-700">Expired or invalid interview link</h1>
        <p class="mt-2 text-sm text-slate-500">This interview link is no longer valid. Please contact the hiring team for a new link.</p>
    <?php endif; ?>
</div>
