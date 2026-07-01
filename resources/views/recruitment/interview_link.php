<?php
/** @var string $state */
/** @var string $token */
/** @var bool $showFeedback */
/** @var array<string,mixed> $room */
// state: valid | room | expired | invalid | completed | revoked | done
?>
<?php if ($state === 'room'): ?>
    <?php
    $messages = $room['messages'] ?? [];
    $asked = (int) ($room['asked'] ?? 0);
    $max = (int) ($room['max_questions'] ?? 12);
    $mins = (int) floor((int) ($room['seconds_remaining'] ?? 0) / 60);
    ?>
    <div class="mx-auto mt-10 flex h-[calc(100vh-7rem)] max-w-2xl flex-col rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <div class="text-lg font-bold tracking-tight text-slate-900">HaHire<span class="text-indigo-600">AI</span> <span class="ms-1 text-sm font-medium text-slate-400">Interview</span></div>
            <div class="text-xs text-slate-400">Question <?= min($asked, $max) ?> / <?= e($max) ?> &middot; ~<?= e($mins) ?> min left</div>
        </div>
        <div class="flex-1 space-y-3 overflow-y-auto px-5 py-4">
            <?php foreach ($messages as $m): ?>
                <?php $isCand = (string) ($m['role'] ?? '') === 'candidate'; ?>
                <div class="flex <?= $isCand ? 'justify-end' : 'justify-start' ?>">
                    <div class="max-w-[80%] rounded-2xl px-4 py-2 text-sm <?= $isCand ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-800' ?>"><?= nl2br(e((string) ($m['content'] ?? ''))) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <form method="post" action="/interview-link/<?= e($token) ?>/answer" class="border-t border-slate-100 p-3">
            <?= csrf_field() ?>
            <div class="flex items-end gap-2">
                <textarea name="answer" rows="2" required autofocus placeholder="Type your answer…" class="flex-1 resize-none rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"></textarea>
                <button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Send</button>
            </div>
            <p class="mt-1.5 text-center text-[11px] text-slate-400">Answer in your own words. Your responses are advisory — a human makes the final decision.</p>
        </form>
    </div>
<?php else: ?>
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
<?php endif; ?>
