<?php
/** @var array<string,mixed> $application */
/** @var array<string,string> $stages */
/** @var string $currentStatus */
/** @var string $nextStep */
/** @var list<array<string,mixed>> $interviews */
/** @var array<string,mixed>|null $pendingInterview */
/** @var bool $deadlinePassed */
/** @var list<array<string,mixed>> $offers */
/** @var array<string,mixed>|null $assessment */
/** @var string $workspaceName */
/** @var string|null $status */

// Happy-path order for the stepper; terminal negatives are shown only if current.
$happyPath = ['applied', 'ai_screening', 'qualified', 'tech_interview', 'manager_interview', 'final_review', 'offer', 'hired'];
$negatives = ['disqualified', 'rejected', 'withdrawn'];
$currentIndex = array_search($currentStatus, $happyPath, true);
$pendingOffers = array_values(array_filter($offers, static fn (array $o): bool => (string) $o['status'] === 'sent'));
$deadlineTs = ! empty($application['deadline_at']) ? strtotime((string) $application['deadline_at'] . ' UTC') : null;
?>
<div class="mb-6">
    <a href="/my-applications" class="text-xs text-slate-400 hover:text-slate-600">← My applications</a>
    <h1 class="mt-1 text-2xl font-semibold text-slate-900"><?= e($application['job_title']) ?></h1>
    <p class="mt-1 text-sm text-slate-500">
        <?php if (! empty($application['location'])): ?><?= e($application['location']) ?> · <?php endif; ?>
        Applied <?= e($application['applied_at']) ?> · at <?= e($workspaceName) ?>
        <?php if (! empty($application['available_from'])): ?> · Can start: <?= e((string) $application['available_from']) ?><?php endif; ?>
    </p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<?php if ($pendingInterview !== null): ?>
    <?php if ($deadlinePassed): ?>
        <div class="mb-4 rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-500 shadow-sm">
            The deadline for this role has passed — the AI interview is now closed.
        </div>
    <?php else: ?>
        <div class="mb-4 rounded-2xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-indigo-900"><?= (string) $pendingInterview['status'] === 'in_progress' ? 'Resume your AI interview' : 'Your AI interview is ready' ?></div>
                    <p class="mt-0.5 text-xs text-indigo-700">
                        Start now, or anytime before the deadline — you can pause and resume.
                        <?php if ($deadlineTs): ?><span class="font-medium">Closes in <span data-countdown="<?= e((string) max(0, $deadlineTs - time())) ?>">…</span> (<?= e(gmdate('Y-m-d H:i', $deadlineTs)) ?> UTC).</span><?php endif; ?>
                    </p>
                </div>
                <a href="/interview/<?= e($pendingInterview['id']) ?>" class="shrink-0 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"><?= (string) $pendingInterview['status'] === 'in_progress' ? 'Continue →' : 'Start now →' ?></a>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <!-- Next step -->
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50/50 p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-indigo-500">Next step</div>
            <p class="mt-1 text-sm text-slate-700"><?= e($nextStep) ?></p>
        </div>

        <!-- Stage map -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">Your progress</h2>
            <ol class="space-y-3">
                <?php foreach ($happyPath as $i => $st): ?>
                    <?php
                    $done = $currentIndex !== false && $i < $currentIndex;
                    $here = $st === $currentStatus;
                    $dot = $done ? 'bg-emerald-500 text-white' : ($here ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-400');
                    ?>
                    <li class="flex items-center gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold <?= $dot ?>"><?= $done ? '✓' : ($i + 1) ?></span>
                        <span class="text-sm <?= $here ? 'font-semibold text-slate-900' : ($done ? 'text-slate-600' : 'text-slate-400') ?>"><?= e($stages[$st]) ?></span>
                        <?php if ($here): ?><span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">you are here</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php if (in_array($currentStatus, $negatives, true)): ?>
                    <li class="flex items-center gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-500 text-xs font-semibold text-white">!</span>
                        <span class="text-sm font-semibold text-rose-700"><?= e($stages[$currentStatus]) ?></span>
                    </li>
                <?php endif; ?>
            </ol>
        </div>

        <!-- What the AI noted -->
        <?php if ($assessment !== null && ! empty($assessment['summary'])): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-slate-900">From your AI interview</h2>
                <p class="text-sm text-slate-600"><?= nl2br(e((string) $assessment['summary'])) ?></p>
                <?php if (! empty($assessment['strengths']) && is_array($assessment['strengths'])): ?>
                    <div class="mt-3">
                        <div class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Highlights</div>
                        <ul class="mt-1 list-inside list-disc text-sm text-slate-600">
                            <?php foreach (array_slice($assessment['strengths'], 0, 4) as $s): ?><li><?= e((string) $s) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <p class="mt-3 text-xs text-slate-400">This is advisory AI analysis. The hiring team makes the final decision.</p>
            </div>
        <?php endif; ?>

        <!-- Interviews -->
        <?php if ($interviews !== []): ?>
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-3"><h2 class="text-sm font-semibold text-slate-900">Interviews</h2></div>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($interviews as $iv): ?>
                        <li class="flex items-center justify-between px-5 py-3 text-sm">
                            <div>
                                <span class="uppercase text-xs font-medium text-slate-500"><?= e($iv['type']) ?></span>
                                <span class="text-slate-600">· <?= e($iv['status']) ?></span>
                                <?php if (! empty($iv['scheduled_at'])): ?><span class="text-xs text-slate-400"> · <?= e($iv['scheduled_at']) ?> UTC</span><?php endif; ?>
                            </div>
                            <?php if ((string) $iv['type'] === 'ai' && $iv['status'] !== 'completed'): ?>
                                <a href="/interview/<?= e($iv['id']) ?>" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700"><?= $iv['status'] === 'in_progress' ? 'Continue interview →' : 'Start interview →' ?></a>
                            <?php elseif (! empty($iv['meeting_link']) && $iv['status'] !== 'completed'): ?>
                                <a href="<?= e($iv['meeting_link']) ?>" target="_blank" rel="noopener" class="text-xs font-medium text-indigo-600 hover:underline">Join →</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="space-y-6">
        <!-- Offers -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Offers</h2>
            <?php if ($offers === []): ?>
                <p class="text-sm text-slate-400">No offers yet.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($offers as $o): ?>
                        <div class="rounded-xl border border-slate-200 p-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold text-slate-900"><?= e($o['title']) ?></span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"><?= e($o['status']) ?></span>
                            </div>
                            <?php if ($o['salary'] !== null): ?><div class="mt-0.5 text-xs text-slate-500"><?= e(number_format((float) $o['salary'])) ?> <?= e($o['currency']) ?></div><?php endif; ?>
                            <?php if (! empty($o['note'])): ?><p class="mt-1 text-xs text-slate-500"><?= e($o['note']) ?></p><?php endif; ?>
                            <?php if ((string) $o['status'] === 'sent'): ?>
                                <div class="mt-2 flex gap-2">
                                    <form method="post" action="/my-offers/<?= e($o['id']) ?>/accept"><?= csrf_field() ?><button class="rounded-lg bg-emerald-600 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-700">Accept</button></form>
                                    <form method="post" action="/my-offers/<?= e($o['id']) ?>/decline"><?= csrf_field() ?><button class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">Decline</button></form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Counter-offer / propose -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-1 text-sm font-semibold text-slate-900">Propose your terms</h2>
            <p class="mb-3 text-xs text-slate-400">Suggest an offer to the company with a short note explaining why.</p>
            <form method="post" action="/my-applications/<?= e($application['id']) ?>/counter-offer" class="space-y-2">
                <?= csrf_field() ?>
                <input name="title" placeholder="Role / title" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <div class="grid grid-cols-2 gap-2">
                    <input name="salary" type="number" min="0" placeholder="Salary" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="currency" value="USD" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <textarea name="note" rows="3" placeholder="Why this offer? (e.g. market rate, experience…)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Send proposal</button>
            </form>
        </div>

        <!-- Withdraw -->
        <?php if (! in_array($currentStatus, ['hired', 'rejected', 'withdrawn'], true)): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-1 text-sm font-semibold text-slate-900">Withdraw</h2>
                <p class="mb-3 text-xs text-slate-400">No longer interested? You can withdraw your application at any time. This can't be undone.</p>
                <form method="post" action="/my-applications/<?= e($application['id']) ?>/withdraw" onsubmit="return confirm('Withdraw your application for “<?= e($application['job_title']) ?>”? This cannot be undone.');">
                    <?= csrf_field() ?>
                    <button class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">Withdraw application</button>
                </form>
            </div>
        <?php elseif ($currentStatus === 'withdrawn'): ?>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-500 shadow-sm">You withdrew this application.</div>
        <?php endif; ?>
    </div>
</div>

<?php if ($deadlineTs): ?>
<script>
document.querySelectorAll('[data-countdown]').forEach(function (el) {
    var s = parseInt(el.getAttribute('data-countdown'), 10) || 0;
    function fmt(t) {
        if (t <= 0) return 'closed';
        var d = Math.floor(t / 86400), h = Math.floor((t % 86400) / 3600), m = Math.floor((t % 3600) / 60), sec = t % 60;
        return (d > 0 ? d + 'd ' : '') + h + 'h ' + m + 'm ' + sec + 's';
    }
    (function tick() { el.textContent = fmt(s); if (s > 0) { s--; setTimeout(tick, 1000); } })();
});
</script>
<?php endif; ?>
