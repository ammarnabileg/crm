<?php
/** @var string $state */
/** @var string $token */
/** @var bool $showFeedback */
// state: valid | expired | invalid | completed | revoked | done
?>
<div class="mx-auto mt-16 max-w-lg rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
    <div class="mb-2 text-xl font-bold tracking-tight text-slate-900">HaHire<span class="text-indigo-600">AI</span></div>
    <?php if ($state === 'valid'): ?>
        <h1 class="mt-4 text-2xl font-semibold text-slate-900">Your AI interview is ready</h1>
        <p class="mt-2 text-sm text-slate-500">This link is valid and single-use. When you're ready, start the interview below.</p>
        <form method="post" action="/interview-link/<?= e($token) ?>/start" class="mt-6">
            <?= csrf_field() ?>
            <button class="rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-700">Start interview</button>
        </form>
    <?php elseif ($state === 'done' || $state === 'completed'): ?>
        <h1 class="mt-4 text-2xl font-semibold text-emerald-700">Interview completed successfully</h1>
        <p class="mt-2 text-sm text-slate-500">Thank you. Your responses have been recorded and shared with the hiring team.</p>
        <?php if ($showFeedback ?? false): ?>
            <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4 text-left">
                <p class="mb-2 text-sm font-semibold text-slate-700">How was your interview experience?</p>
                <form method="post" action="/interview-link/<?= e($token) ?>/feedback" class="space-y-2">
                    <?= csrf_field() ?>
                    <div class="flex items-center gap-2">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <label class="cursor-pointer text-sm"><input type="radio" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?> class="me-0.5"><?= $i ?>★</label>
                        <?php endfor; ?>
                    </div>
                    <textarea name="comment" rows="2" placeholder="Any comments? (optional)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Send feedback</button>
                </form>
            </div>
        <?php else: ?>
            <p class="mt-2 text-xs text-slate-400">You can close this page.</p>
        <?php endif; ?>
    <?php else: ?>
        <h1 class="mt-4 text-2xl font-semibold text-rose-700">Expired or invalid interview link</h1>
        <p class="mt-2 text-sm text-slate-500">This interview link is no longer valid. Please contact the hiring team for a new link.</p>
    <?php endif; ?>
</div>
