<?php
/** @var array<string,mixed> $interview */
/** @var array<string,string> $dimensions */
/** @var bool $canEvaluate */
/** @var bool $canSchedule */
/** @var string|null $status */

$details = $interview['details_decoded'] ?? [];
$ratings = is_array($details['ratings'] ?? null) ? $details['ratings'] : [];
$completed = ($interview['status'] ?? '') === 'completed';
$mode = (string) ($interview['mode'] ?? 'online');
?>
<div class="mb-6 flex items-start justify-between">
    <div>
        <a href="/human-interviews" class="text-xs text-slate-400 hover:text-slate-600">← Human Interviews</a>
        <h1 class="mt-1 text-2xl font-semibold text-slate-900"><?= e($interview['candidate_name']) ?></h1>
        <p class="mt-1 text-sm text-slate-500">
            <?= e($interview['job_title']) ?>
            · <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium uppercase text-slate-500"><?= e($mode) ?></span>
            · <?= e($interview['status']) ?>
            <?php if (! empty($interview['scheduled_at'])): ?> · <?= e($interview['scheduled_at']) ?> UTC<?php endif; ?>
        </p>
        <?php if (! empty($interview['meeting_link'])): ?>
            <a href="<?= e($interview['meeting_link']) ?>" target="_blank" rel="noopener" class="mt-1 inline-block text-sm text-indigo-600 hover:underline">Join meeting →</a>
        <?php endif; ?>
    </div>
    <a href="/candidates/<?= e($interview['candidate_user_id']) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Candidate file</a>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <?php if ($completed): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-900">Recorded evaluation</h2>
                    <?php $sc = (int) $interview['score'] >= 75 ? 'text-emerald-700' : ((int) $interview['score'] >= 55 ? 'text-amber-700' : 'text-rose-700'); ?>
                    <span class="text-lg font-semibold <?= $sc ?>"><?= e($interview['score']) ?>/100 · <?= e($interview['recommendation'] ?? '') ?></span>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                    <?php foreach ($dimensions as $key => $label): ?>
                        <div class="flex items-center justify-between border-b border-slate-50 py-1">
                            <dt class="text-slate-500"><?= e($label) ?></dt>
                            <dd class="font-semibold text-slate-800"><?= isset($ratings[$key]) ? e($ratings[$key]) . '/5' : '—' ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
                <?php if (! empty($details['strengths'])): ?><p class="mt-4 text-sm"><span class="font-medium text-emerald-700">Strengths:</span> <span class="text-slate-700"><?= nl2br(e($details['strengths'])) ?></span></p><?php endif; ?>
                <?php if (! empty($details['weaknesses'])): ?><p class="mt-2 text-sm"><span class="font-medium text-rose-700">Weaknesses:</span> <span class="text-slate-700"><?= nl2br(e($details['weaknesses'])) ?></span></p><?php endif; ?>
                <?php if (! empty($details['notes'])): ?><p class="mt-2 text-sm"><span class="font-medium text-slate-700">Notes:</span> <span class="text-slate-700"><?= nl2br(e($details['notes'])) ?></span></p><?php endif; ?>
                <p class="mt-3 text-xs text-slate-400">You can re-submit below to revise the evaluation.</p>
            </div>
        <?php endif; ?>

        <?php if ($canEvaluate): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold text-slate-900"><?= $completed ? 'Revise evaluation' : 'Submit evaluation' ?></h2>
                <form method="post" action="/human-interviews/<?= e($interview['id']) ?>/evaluate" class="space-y-4">
                    <?= csrf_field() ?>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <?php foreach ($dimensions as $key => $label): ?>
                            <label class="block text-xs font-medium text-slate-600"><?= e($label) ?>
                                <select name="rating_<?= e($key) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <option value="<?= $i ?>" <?= (int) ($ratings[$key] ?? 3) === $i ? 'selected' : '' ?>><?= $i ?> — <?= ['', 'Poor', 'Fair', 'Good', 'Strong', 'Excellent'][$i] ?></option>
                                    <?php endfor; ?>
                                </select>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <label class="block text-xs font-medium text-slate-600">Strengths
                        <textarea name="strengths" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="What stood out?"><?= e($details['strengths'] ?? '') ?></textarea>
                    </label>
                    <label class="block text-xs font-medium text-slate-600">Weaknesses
                        <textarea name="weaknesses" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Concerns or gaps?"><?= e($details['weaknesses'] ?? '') ?></textarea>
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-xs font-medium text-slate-600">Overall rating
                            <select name="overall" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?= $i ?>" <?= (int) ($details['overall'] ?? 3) === $i ? 'selected' : '' ?>><?= $i ?> / 5</option>
                                <?php endfor; ?>
                            </select>
                        </label>
                        <label class="block text-xs font-medium text-slate-600">Recommendation
                            <select name="recommendation" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <?php foreach (['advance' => 'Advance', 'hold' => 'Hold', 'reject' => 'Reject'] as $val => $lbl): ?>
                                    <option value="<?= $val ?>" <?= ($interview['recommendation'] ?? 'hold') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <label class="block text-xs font-medium text-slate-600">Notes
                        <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Anything else the decision-maker should know."><?= e($details['notes'] ?? '') ?></textarea>
                    </label>

                    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save evaluation</button>
                    <p class="text-xs text-slate-400">This is advisory input for the human decision-maker — it doesn't move the candidate by itself.</p>
                </form>
            </div>
        <?php else: ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-400 shadow-sm">You don't have permission to submit evaluations.</div>
        <?php endif; ?>
    </div>

    <?php if ($canSchedule): ?>
        <div class="space-y-6 self-start">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Reschedule / edit</h2>
                <form method="post" action="/human-interviews/<?= e($interview['id']) ?>/reschedule" class="space-y-3">
                    <?= csrf_field() ?>
                    <label class="block text-xs font-medium text-slate-600">Format
                        <select name="mode" data-mode-select class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="online" <?= $mode !== 'onsite' ? 'selected' : '' ?>>Online</option>
                            <option value="onsite" <?= $mode === 'onsite' ? 'selected' : '' ?>>Onsite</option>
                        </select>
                    </label>
                    <label class="block text-xs font-medium text-slate-600" data-meeting-link>Meeting link
                        <input name="meeting_link" type="url" value="<?= e($interview['meeting_link'] ?? '') ?>" placeholder="https://…" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </label>
                    <label class="block text-xs font-medium text-slate-600">Date &amp; time (UTC)
                        <input name="scheduled_at" type="datetime-local" value="<?= e(! empty($interview['scheduled_at']) ? str_replace(' ', 'T', substr((string) $interview['scheduled_at'], 0, 16)) : '') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </label>
                    <button class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Update</button>
                </form>
            </div>

            <form method="post" action="/human-interviews/<?= e($interview['id']) ?>/archive" onsubmit="return confirm('Archive this interview?');">
                <?= csrf_field() ?>
                <button class="text-xs font-medium text-rose-600 hover:text-rose-700">Archive interview</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
    (function () {
        document.querySelectorAll('[data-mode-select]').forEach(function (sel) {
            var link = sel.closest('form').querySelector('[data-meeting-link]');
            if (!link) return;
            var sync = function () { link.style.display = sel.value === 'onsite' ? 'none' : ''; };
            sel.addEventListener('change', sync); sync();
        });
    })();
</script>
