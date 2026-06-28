<?php
/** @var array<string,mixed> $profile */
/** @var list<array<string,mixed>> $applications */
/** @var list<array<string,mixed>> $notes */
/** @var list<string> $tags */
/** @var bool $canNote */
/** @var bool $canTag */
/** @var list<array<string,mixed>> $interviews */
/** @var int|null $score */
?>
<div class="mb-6">
    <a href="/candidates" class="text-sm text-indigo-600 hover:underline">&larr; Candidates</a>
    <div class="mt-1 flex items-center gap-3">
        <h1 class="text-2xl font-semibold text-slate-900"><?= e($profile['name']) ?></h1>
        <?php if ($score !== null): ?>
            <?php $sc = $score >= 75 ? 'bg-emerald-50 text-emerald-700' : ($score >= 55 ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700'); ?>
            <span class="rounded-full px-3 py-1 text-sm font-semibold <?= $sc ?>" title="Average interview score in this workspace">Score: <?= e($score) ?>/100</span>
        <?php endif; ?>
    </div>
    <p class="text-sm text-slate-500"><?= e($profile['email']) ?></p>
    <div class="mt-2 flex flex-wrap items-center gap-2">
        <?php foreach ($tags as $tag): ?>
            <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700"><?= e($tag) ?></span>
        <?php endforeach; ?>
        <?php if ($canAi ?? false): ?>
            <form method="post" action="/candidates/<?= e($profile['user_id']) ?>/ai-summary">
                <?= csrf_field() ?>
                <button class="rounded-full bg-slate-900 px-3 py-1 text-xs font-medium text-white hover:bg-slate-700">✦ Generate AI summary</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (! empty($status)): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<?php
/** @var array<string,mixed>|null $assessment */
/** @var array<string,array{label:string,weight:int}> $skillCatalog */
$a = $assessment ?? null;
$bandMeta = [
    'strong' => ['Strong recommend', 'bg-emerald-50 text-emerald-700'],
    'suitable' => ['Suitable', 'bg-emerald-50 text-emerald-700'],
    'maybe' => ['Maybe suitable', 'bg-amber-50 text-amber-700'],
    'unsuitable' => ['Not suitable', 'bg-rose-50 text-rose-700'],
];
?>
<?php if ($a !== null): ?>
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900">AI Assessment <span class="font-normal text-slate-400">(advisory — you decide)</span></h2>
            <?php $bm = $bandMeta[(string) $a['recommendation']] ?? ['—', 'bg-slate-100 text-slate-500']; ?>
            <div class="flex items-center gap-2">
                <span class="text-lg font-bold text-slate-900"><?= e($a['fit_score']) ?>/100</span>
                <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $bm[1] ?>"><?= e($bm[0]) ?></span>
            </div>
        </div>
        <p class="mb-4 text-sm text-slate-600"><?= e($a['summary']) ?></p>

        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Skills</h3>
                <div class="space-y-1.5">
                    <?php foreach (($a['skills'] ?? []) as $key => $s): ?>
                        <?php $score = (int) ($s['score'] ?? 0); $cls = $score >= 75 ? 'bg-emerald-500' : ($score >= 55 ? 'bg-amber-500' : 'bg-rose-400'); ?>
                        <div class="flex items-center gap-2 text-xs">
                            <div class="w-32 shrink-0 text-slate-500"><?= e($skillCatalog[$key]['label'] ?? $key) ?></div>
                            <div class="h-2 grow rounded bg-slate-100"><div class="h-2 rounded <?= $cls ?>" style="width: <?= $score ?>%"></div></div>
                            <div class="w-7 shrink-0 text-right font-medium text-slate-700"><?= $score ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Behaviour</h3>
                    <?php $b = $a['behavior'] ?? []; ?>
                    <p class="text-xs text-slate-600">DISC <strong><?= e($b['disc'] ?? '—') ?></strong> · Big Five: <?= e($b['big_five'] ?? '—') ?> · Leadership: <?= e($b['leadership_style'] ?? '—') ?></p>
                    <p class="text-xs text-slate-500">Growth <?= e($b['growth'] ?? '—') ?>/100 · Stress tolerance <?= e($b['stress_tolerance'] ?? '—') ?>/100</p>
                </div>
                <div>
                    <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Strengths / Gaps</h3>
                    <p class="text-xs text-emerald-700">+ <?= e(implode(', ', (array) ($a['strengths'] ?? []))) ?></p>
                    <p class="text-xs text-rose-600">– <?= e(implode(', ', (array) ($a['weaknesses'] ?? []))) ?></p>
                </div>
                <?php if (($a['red_flags'] ?? []) !== []): ?>
                    <div>
                        <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Red flags</h3>
                        <?php foreach ($a['red_flags'] as $rf): ?>
                            <?php $dot = ($rf['severity'] ?? '') === 'high' ? 'text-rose-600' : (($rf['severity'] ?? '') === 'medium' ? 'text-amber-600' : 'text-yellow-600'); ?>
                            <p class="text-xs text-slate-600"><span class="<?= $dot ?>">●</span> <?= e($rf['note'] ?? '') ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Applications in this workspace</h2>
            <?php if ($applications === []): ?>
                <p class="text-sm text-slate-400">No applications.</p>
            <?php else: ?>
                <ul class="space-y-1 text-sm">
                    <?php foreach ($applications as $a): ?>
                        <li class="flex items-center justify-between gap-2 rounded-md bg-slate-50 px-3 py-2">
                            <span class="font-medium text-slate-800"><?= e($a['job_title']) ?></span>
                            <?php if (($canSetStatus ?? false) && ! empty($a['id'])): ?>
                                <form method="post" action="/applications/<?= e($a['id']) ?>/status">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="redirect_to" value="/candidates/<?= e($profile['user_id']) ?>">
                                    <select name="status" onchange="this.form.submit()" class="rounded border border-slate-300 px-2 py-1 text-xs">
                                        <?php foreach (($statuses ?? []) as $sk => $sl): ?>
                                            <option value="<?= e($sk) ?>" <?= (string) $a['status'] === $sk ? 'selected' : '' ?>><?= e($sl) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            <?php else: ?>
                                <span class="text-slate-500"><?= e($statuses[$a['status']] ?? $a['stage'] ?? $a['status']) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Interviews</h2>
            <?php if (($canScheduleInterview ?? false) && $applications !== []): ?>
                <form method="post" action="/candidates/<?= e($profile['user_id']) ?>/interviews" class="mb-4 flex flex-wrap items-center gap-2">
                    <?= csrf_field() ?>
                    <select name="application_id" class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
                        <?php foreach ($applications as $a): ?>
                            <option value="<?= e($a['id']) ?>"><?= e($a['job_title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="type" class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
                        <option value="human">Human</option>
                        <option value="ai">AI</option>
                    </select>
                    <button class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Schedule</button>
                </form>
            <?php endif; ?>
            <?php if (($interviews ?? []) === []): ?>
                <p class="text-sm text-slate-400">No interviews yet.</p>
            <?php else: ?>
                <ul class="space-y-3 text-sm">
                    <?php foreach ($interviews as $iv): ?>
                        <li class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="rounded bg-slate-200 px-1.5 py-0.5 text-xs font-medium uppercase text-slate-600"><?= e($iv['type']) ?></span>
                                    <span class="font-medium text-slate-800"><?= e($iv['job_title']) ?></span>
                                    <span class="text-xs text-slate-400">· <?= e($iv['status']) ?></span>
                                </div>
                                <?php if ($iv['score'] !== null): ?>
                                    <span class="text-xs font-semibold text-slate-700"><?= e($iv['score']) ?>/100 · <?= e($iv['recommendation'] ?? '—') ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (! empty($iv['summary'])): ?><div class="mt-1 text-xs text-slate-500"><?= e($iv['summary']) ?></div><?php endif; ?>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <?php if ($iv['type'] === 'ai' && $iv['status'] === 'scheduled' && ($canRunAiInterview ?? false)): ?>
                                    <form method="post" action="/interviews/<?= e($iv['id']) ?>/ai-run">
                                        <?= csrf_field() ?>
                                        <button class="rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white hover:bg-slate-700">✦ Run AI interview</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($canEvaluate ?? false): ?>
                                    <form method="post" action="/interviews/<?= e($iv['id']) ?>/evaluate" class="flex flex-wrap items-center gap-1">
                                        <?= csrf_field() ?>
                                        <input name="score" type="number" min="0" max="100" placeholder="0-100" class="w-20 rounded border border-slate-300 px-2 py-1 text-xs">
                                        <select name="recommendation" class="rounded border border-slate-300 px-1 py-1 text-xs">
                                            <option value="advance">advance</option>
                                            <option value="hold">hold</option>
                                            <option value="reject">reject</option>
                                        </select>
                                        <input name="summary" placeholder="evaluation note" class="w-40 rounded border border-slate-300 px-2 py-1 text-xs">
                                        <button class="rounded-md bg-indigo-600 px-2 py-1 text-xs font-medium text-white hover:bg-indigo-700">Save</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Notes</h2>
            <?php if ($canNote): ?>
                <form method="post" action="/candidates/<?= e($profile['user_id']) ?>/notes" class="mb-4 flex gap-2">
                    <?= csrf_field() ?>
                    <input name="body" required placeholder="Add a private note…" class="grow rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    <button class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add</button>
                </form>
            <?php endif; ?>
            <?php if ($notes === []): ?>
                <p class="text-sm text-slate-400">No notes yet.</p>
            <?php else: ?>
                <ul class="space-y-2 text-sm">
                    <?php foreach ($notes as $n): ?>
                        <li class="rounded-lg bg-slate-50 px-3 py-2">
                            <div class="text-slate-700"><?= e($n['body']) ?></div>
                            <div class="mt-1 text-xs text-slate-400"><?= e($n['author'] ?? 'system') ?> · <?= e($n['created_at']) ?> UTC</div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-900">CVs &amp; files</h2>
        <?php if (($canUploadFile ?? false)): ?>
            <form method="post" action="/files/upload" enctype="multipart/form-data" class="mb-3 space-y-2">
                <?= csrf_field() ?>
                <input type="hidden" name="entity_type" value="candidate_profile">
                <input type="hidden" name="entity_id" value="<?= e($profile['profile_id']) ?>">
                <input type="hidden" name="redirect_to" value="/candidates/<?= e($profile['user_id']) ?>">
                <input type="file" name="file" required class="block w-full text-xs text-slate-600 file:mr-2 file:rounded file:border-0 file:bg-slate-800 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-white">
                <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">Upload</button>
            </form>
        <?php endif; ?>
        <?php if (($files ?? []) === []): ?>
            <p class="text-sm text-slate-400">No files yet.</p>
        <?php else: ?>
            <ul class="space-y-1 text-sm">
                <?php foreach ($files as $f): ?>
                    <li class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-1.5">
                        <span class="min-w-0 truncate">
                            <?php if ($canViewFile ?? false): ?>
                                <a href="/files/<?= e($f['id']) ?>/download" class="text-indigo-600 hover:underline"><?= e($f['original_name']) ?></a>
                            <?php else: ?>
                                <?= e($f['original_name']) ?>
                            <?php endif; ?>
                            <span class="text-xs text-slate-400"><?= e(number_format(((int) $f['size_bytes']) / 1024, 1)) ?> KB</span>
                        </span>
                        <?php if ($canDeleteFile ?? false): ?>
                            <form method="post" action="/files/<?= e($f['id']) ?>/delete" onsubmit="return confirm('Delete this file?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="redirect_to" value="/candidates/<?= e($profile['user_id']) ?>">
                                <button class="text-xs font-medium text-rose-600 hover:text-rose-700">×</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Tags</h2>
        <?php if ($canTag): ?>
            <form method="post" action="/candidates/<?= e($profile['user_id']) ?>/tags" class="flex gap-2">
                <?= csrf_field() ?>
                <input name="tag" required placeholder="e.g. strong-fit" class="grow rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                <button class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-700">Tag</button>
            </form>
        <?php else: ?>
            <p class="text-sm text-slate-400">You don't have permission to tag.</p>
        <?php endif; ?>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Offers</h2>
        <?php foreach (($offers ?? []) as $offer): ?>
            <div class="mb-2 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                <span><?= e($offer['title'] ?: 'Offer') ?> · <span class="text-slate-500"><?= e($offer['status']) ?></span></span>
                <?php if (($canDecide ?? false) && $offer['status'] === 'sent'): ?>
                    <form method="post" action="/offers/<?= e($offer['id']) ?>/accept">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= e($profile['user_id']) ?>">
                        <button class="rounded-md bg-emerald-600 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-700">Mark accepted → hire</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (($offers ?? []) === []): ?><p class="mb-3 text-sm text-slate-400">No offers yet.</p><?php endif; ?>
        <?php if ($canOffer ?? false): ?>
            <form method="post" action="/candidates/<?= e($profile['user_id']) ?>/offer" class="mt-2 flex gap-2">
                <?= csrf_field() ?>
                <input name="title" placeholder="Offer title" class="grow rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                <button class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Make &amp; send</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="mb-3 text-sm font-semibold text-slate-900">Timeline <span class="text-xs font-normal text-slate-400">— this workspace only</span></h2>
    <?php if (($timeline ?? []) === []): ?>
        <p class="text-sm text-slate-400">No activity yet.</p>
    <?php else: ?>
        <ol class="relative space-y-3 border-s border-slate-200 ps-5 text-sm">
            <?php foreach ($timeline as $ev): ?>
                <li class="relative">
                    <span class="absolute -start-[1.42rem] top-1.5 h-2 w-2 rounded-full bg-indigo-400"></span>
                    <div class="text-slate-700"><?= e($ev['label']) ?></div>
                    <div class="text-xs text-slate-400"><?= e($ev['type']) ?> · <?= e($ev['at']) ?> UTC</div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>
