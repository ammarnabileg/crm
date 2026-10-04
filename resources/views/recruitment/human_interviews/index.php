<?php
/** @var list<array<string,mixed>> $interviews */
/** @var list<array<string,mixed>> $applications */
/** @var bool $canSchedule */
/** @var string $query */
/** @var string|null $status */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Human Interviews</h1>
    <p class="mt-1 text-sm text-slate-500">Panel interviews you schedule and score yourself — online or onsite. AI is advisory; the human decision wins.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-4">
        <form method="get" action="/human-interviews" class="flex gap-2">
            <input name="q" value="<?= e($query) ?>" placeholder="Search by candidate or job…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Search</button>
            <?php if ($query !== ''): ?><a href="/human-interviews" class="rounded-lg px-3 py-2 text-sm text-slate-400 hover:text-slate-600">Clear</a><?php endif; ?>
        </form>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <?php if ($interviews === []): ?>
                <p class="px-5 py-6 text-sm text-slate-400"><?= $query !== '' ? 'No interviews match your search.' : 'No human interviews yet. Schedule one on the right.' ?></p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($interviews as $iv): ?>
                        <li class="flex items-center justify-between px-5 py-3 text-sm">
                            <div>
                                <a href="/human-interviews/<?= e($iv['id']) ?>" class="font-medium text-indigo-600 hover:underline"><?= e($iv['candidate_name']) ?></a>
                                <span class="text-slate-400">· <?= e($iv['job_title']) ?></span>
                                <div class="mt-0.5 text-xs text-slate-400">
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 font-medium uppercase text-slate-500"><?= e($iv['mode'] ?? 'online') ?></span>
                                    <?= e($iv['status']) ?><?php if (! empty($iv['scheduled_at'])): ?> · <?= e($iv['scheduled_at']) ?> UTC<?php endif; ?>
                                    <?php if (! empty($iv['interviewer_name'])): ?> · with <?= e($iv['interviewer_name']) ?><?php endif; ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <?php if ($iv['score'] !== null): ?>
                                    <?php $sc = (int) $iv['score'] >= 75 ? 'text-emerald-700' : ((int) $iv['score'] >= 55 ? 'text-amber-700' : 'text-rose-700'); ?>
                                    <span class="text-sm font-semibold <?= $sc ?>"><?= e($iv['score']) ?>/100</span>
                                    <div class="text-xs text-slate-400"><?= e($iv['recommendation'] ?? '') ?></div>
                                <?php else: ?>
                                    <span class="text-xs font-medium text-amber-600">awaiting score</span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($canSchedule): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm self-start">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Schedule a human interview</h2>
            <?php if ($applications === []): ?>
                <p class="text-sm text-slate-400">No applicants yet. Candidates appear here once they apply to a job.</p>
            <?php else: ?>
                <form method="post" action="/human-interviews" class="space-y-3">
                    <?= csrf_field() ?>
                    <label class="block text-xs font-medium text-slate-600">Applicant
                        <select name="application_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Choose an applicant…</option>
                            <?php foreach ($applications as $a): ?>
                                <option value="<?= e($a['id']) ?>"><?= e($a['candidate_name']) ?> — <?= e($a['job_title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-xs font-medium text-slate-600">Format
                        <select name="mode" data-mode-select class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="online">Online</option>
                            <option value="onsite">Onsite</option>
                        </select>
                    </label>
                    <label class="block text-xs font-medium text-slate-600" data-meeting-link>Meeting link
                        <input name="meeting_link" type="url" placeholder="https://meet.example.com/…" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </label>
                    <label class="block text-xs font-medium text-slate-600">Date &amp; time (UTC)
                        <input name="scheduled_at" type="datetime-local" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </label>
                    <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Schedule interview</button>
                </form>
                <script>
                    (function () {
                        var sel = document.querySelector('[data-mode-select]');
                        var link = document.querySelector('[data-meeting-link]');
                        if (!sel || !link) return;
                        var sync = function () { link.style.display = sel.value === 'onsite' ? 'none' : ''; };
                        sel.addEventListener('change', sync); sync();
                    })();
                </script>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
