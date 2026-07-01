<?php
/** @var array<string,mixed> $profile */
/** @var array<string,mixed> $details */
/** @var list<array<string,mixed>> $statusHistory */
/** @var list<array<string,mixed>> $applications */
/** @var list<array<string,mixed>> $notes */
/** @var list<string> $tags */
/** @var list<array<string,mixed>> $interviews */
/** @var array<string,mixed>|null $assessment */
/** @var array<string,array{label:string,weight:int}> $skillCatalog */
/** @var int|null $score */

$dv = static fn (string $k): string => trim((string) ($details[$k] ?? ''));
$hasDetails = $dv('education') || $dv('languages') || $dv('skills') || $dv('certifications') || $dv('current_salary') || $dv('expected_salary') || $dv('availability') || $dv('location');
$ax = $assessment ?? null;
$bandMeta = [
    'strong' => ['Strong recommend', 'bg-emerald-50 text-emerald-700'],
    'suitable' => ['Suitable', 'bg-emerald-50 text-emerald-700'],
    'maybe' => ['Maybe suitable', 'bg-amber-50 text-amber-700'],
    'unsuitable' => ['Not suitable', 'bg-rose-50 text-rose-700'],
];
$initial = mb_strtoupper(mb_substr(trim((string) $profile['name']) ?: '?', 0, 1));
$tabs = [
    'overview' => 'Overview',
    'first_impression' => 'First Impression',
    'assessment' => 'Assessment',
    'applications' => 'Applications & Interviews',
    'profile' => 'Profile & CV',
    'engagement' => 'Notes & Offers',
    'timeline' => 'Timeline',
];
$first = array_key_first($tabs);
$btn = static function (string $key, string $label) use ($first): string {
    $active = $key === $first ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700';
    return '<button type="button" data-tab="' . $key . '" class="wf-tab whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium ' . $active . '">' . e($label) . '</button>';
};
$panel = static fn (string $key, string $extra = ''): string => 'data-panel="' . $key . '" class="' . ($key === $first ? '' : 'hidden ') . $extra . '"';
$card = 'rounded-2xl border border-slate-200 bg-white p-6 shadow-sm';
$h2 = 'mb-3 text-sm font-semibold text-slate-900';
$lbl = 'text-xs font-semibold uppercase tracking-wide text-slate-400';
?>
<div id="cand-profile">
    <a href="/candidates" data-pjax class="text-sm text-indigo-600 hover:underline">&larr; Candidates</a>

    <!-- Profile header -->
    <div class="mt-2 <?= $card ?>">
        <div class="flex flex-wrap items-start gap-4">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-indigo-600 text-xl font-bold text-white shadow-sm"><?= e($initial) ?></span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold text-slate-900"><?= e($profile['name']) ?></h1>
                    <?php if ($ax !== null): ?>
                        <?php $bm = $bandMeta[(string) $ax['recommendation']] ?? ['—', 'bg-slate-100 text-slate-500']; ?>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $bm[1] ?>"><?= e($bm[0]) ?></span>
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
            <!-- Score ring -->
            <div class="flex shrink-0 items-center gap-4">
                <?php
                $shown = $ax !== null ? (int) $ax['fit_score'] : ($score ?? null);
                if ($shown !== null):
                    $ringColor = $shown >= 75 ? '#10b981' : ($shown >= 55 ? '#f59e0b' : '#f43f5e');
                ?>
                    <div class="relative h-20 w-20" title="Overall fit">
                        <svg viewBox="0 0 36 36" class="h-20 w-20 -rotate-90">
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e2e8f0" stroke-width="3"></circle>
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke="<?= $ringColor ?>" stroke-width="3" stroke-linecap="round" stroke-dasharray="<?= $shown ?>, 100"></circle>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-lg font-bold text-slate-900"><?= e($shown) ?></span>
                            <span class="text-[9px] uppercase tracking-wide text-slate-400">/100</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (! empty($status)): ?><div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

    <!-- Tab nav -->
    <div class="mt-5 border-b border-slate-200">
        <nav class="flex gap-6 overflow-x-auto">
            <?php foreach ($tabs as $k => $label) {
                echo $btn($k, $label);
            } ?>
        </nav>
    </div>

    <div class="mt-6">
        <!-- ── Overview ─────────────────────────────────────────────── -->
        <div <?= $panel('overview') ?>>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="<?= $card ?>"><div class="<?= $lbl ?>">Applications</div><div class="mt-1 text-2xl font-semibold text-slate-900"><?= count($applications) ?></div></div>
                <div class="<?= $card ?>"><div class="<?= $lbl ?>">Interviews</div><div class="mt-1 text-2xl font-semibold text-slate-900"><?= count($interviews ?? []) ?></div></div>
                <div class="<?= $card ?>"><div class="<?= $lbl ?>">Avg interview score</div><div class="mt-1 text-2xl font-semibold text-slate-900"><?= $score !== null ? e($score) . '<span class="text-sm text-slate-400">/100</span>' : '<span class="text-base text-slate-400">—</span>' ?></div></div>
            </div>
            <?php if ($ax !== null): ?>
                <div class="mt-4 <?= $card ?>">
                    <h2 class="<?= $h2 ?>">AI summary <span class="font-normal text-slate-400">(advisory — you decide)</span></h2>
                    <p class="text-sm text-slate-600"><?= e($ax['summary']) ?></p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <p class="text-xs text-emerald-700">+ <?= e(implode(', ', (array) ($ax['strengths'] ?? []))) ?: '—' ?></p>
                        <p class="text-xs text-rose-600">– <?= e(implode(', ', (array) ($ax['weaknesses'] ?? []))) ?: '—' ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="mt-4 <?= $card ?> text-sm text-slate-400">No AI assessment yet — run an AI interview to generate one.</div>
            <?php endif; ?>
            <?php if (($statusHistory ?? []) !== []): ?>
                <div class="mt-4 <?= $card ?>">
                    <h2 class="<?= $h2 ?>">Stage history</h2>
                    <ol class="space-y-2 text-xs">
                        <?php foreach ($statusHistory as $hh): ?>
                            <li class="border-l-2 border-slate-200 pl-3">
                                <div class="font-medium text-slate-700"><?= e(\HaHireAI\Modules\Recruitment\Domain\ApplicationStatus::label((string) ($hh['from_status'] ?? 'applied'))) ?> &rarr; <?= e(\HaHireAI\Modules\Recruitment\Domain\ApplicationStatus::label((string) $hh['to_status'])) ?></div>
                                <div class="text-slate-400"><?= e($hh['created_at']) ?><?php if (! empty($hh['changed_by_name'])): ?> · <?= e($hh['changed_by_name']) ?><?php endif; ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── First Impression (zero-AI gate) ─────────────────────── -->
        <div <?= $panel('first_impression') ?>>
            <?php $fi = $firstImpression ?? null; ?>
            <?php if ($fi === null): ?>
                <div class="<?= $card ?> text-sm text-slate-400">No first-impression report — this candidate applied to a role without the First Impression filter enabled.</div>
            <?php else: ?>
                <?php $fr = $fi['report'] ?? []; ?>
                <?php if ((string) ($fr['decision'] ?? '') === 'filtered' && (int) ($fr['overridden'] ?? 0) === 0 && ($canOverrideFi ?? false)): ?>
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                        <p class="text-sm text-amber-800">This candidate was <strong>filtered before the AI interview</strong>. You can override and let them proceed to the AI interview.</p>
                        <form method="post" action="/first-impression/<?= e((string) $fr['id']) ?>/override">
                            <?= csrf_field() ?>
                            <button class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Override → allow AI interview</button>
                        </form>
                    </div>
                <?php endif; ?>
                <?php
                // Render the shared report partial in an ISOLATED scope so its
                // locals (e.g. $details) never clobber this view's variables.
                (static function (array $full, bool $readonly): void {
                    include __DIR__ . '/../_first_impression.php';
                })($fi, false);
                ?>
            <?php endif; ?>
        </div>

        <!-- ── Assessment ───────────────────────────────────────────── -->
        <div <?= $panel('assessment') ?>>
            <?php if ($ax === null): ?>
                <div class="<?= $card ?> text-sm text-slate-400">No AI assessment yet. Schedule and run an AI interview to generate skills, behaviour and red-flag analysis.</div>
            <?php else: ?>
                <?php $bm = $bandMeta[(string) $ax['recommendation']] ?? ['—', 'bg-slate-100 text-slate-500']; ?>
                <div class="<?= $card ?>">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="<?= $h2 ?> mb-0">AI Assessment <span class="font-normal text-slate-400">(advisory — you decide)</span></h2>
                        <div class="flex items-center gap-2"><span class="text-lg font-bold text-slate-900"><?= e($ax['fit_score']) ?>/100</span><span class="rounded-full px-3 py-1 text-xs font-semibold <?= $bm[1] ?>"><?= e($bm[0]) ?></span></div>
                    </div>
                    <p class="mb-4 text-sm text-slate-600"><?= e($ax['summary']) ?></p>
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <h3 class="mb-2 <?= $lbl ?>">Skills</h3>
                            <div class="space-y-1.5">
                                <?php foreach (($ax['skills'] ?? []) as $key => $s): ?>
                                    <?php $sv = (int) ($s['score'] ?? 0); $cls = $sv >= 75 ? 'bg-emerald-500' : ($sv >= 55 ? 'bg-amber-500' : 'bg-rose-400'); ?>
                                    <div class="flex items-center gap-2 text-xs">
                                        <div class="w-32 shrink-0 text-slate-500"><?= e($skillCatalog[$key]['label'] ?? $key) ?></div>
                                        <div class="h-2 grow rounded bg-slate-100"><div class="h-2 rounded <?= $cls ?>" style="width: <?= $sv ?>%"></div></div>
                                        <div class="w-7 shrink-0 text-right font-medium text-slate-700"><?= $sv ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <h3 class="mb-1 <?= $lbl ?>">Behaviour</h3>
                                <?php $b = $ax['behavior'] ?? []; ?>
                                <p class="text-xs text-slate-600">DISC <strong><?= e($b['disc'] ?? '—') ?></strong> · Big Five: <?= e($b['big_five'] ?? '—') ?> · Leadership: <?= e($b['leadership_style'] ?? '—') ?></p>
                                <p class="text-xs text-slate-500">Growth <?= e($b['growth'] ?? '—') ?>/100 · Stress tolerance <?= e($b['stress_tolerance'] ?? '—') ?>/100</p>
                            </div>
                            <div>
                                <h3 class="mb-1 <?= $lbl ?>">Strengths / Gaps</h3>
                                <p class="text-xs text-emerald-700">+ <?= e(implode(', ', (array) ($ax['strengths'] ?? []))) ?></p>
                                <p class="text-xs text-rose-600">– <?= e(implode(', ', (array) ($ax['weaknesses'] ?? []))) ?></p>
                            </div>
                            <?php if (($ax['red_flags'] ?? []) !== []): ?>
                                <div>
                                    <h3 class="mb-1 <?= $lbl ?>">Red flags</h3>
                                    <?php foreach ($ax['red_flags'] as $rf): ?>
                                        <?php $dot = ($rf['severity'] ?? '') === 'high' ? 'text-rose-600' : (($rf['severity'] ?? '') === 'medium' ? 'text-amber-600' : 'text-yellow-600'); ?>
                                        <p class="text-xs text-slate-600"><span class="<?= $dot ?>">●</span> <?= e($rf['note'] ?? '') ?></p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Applications & Interviews ────────────────────────────── -->
        <div <?= $panel('applications', 'space-y-4') ?>>
            <div class="<?= $card ?>">
                <h2 class="<?= $h2 ?>">Applications in this workspace</h2>
                <?php if ($applications === []): ?>
                    <p class="text-sm text-slate-400">No applications.</p>
                <?php else: ?>
                    <ul class="space-y-1 text-sm">
                        <?php foreach ($applications as $app): ?>
                            <li class="flex items-center justify-between gap-2 rounded-md bg-slate-50 px-3 py-2">
                                <span class="font-medium text-slate-800"><?= e($app['job_title']) ?></span>
                                <?php if (($canSetStatus ?? false) && ! empty($app['id'])): ?>
                                    <form method="post" action="/applications/<?= e($app['id']) ?>/status">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="redirect_to" value="/candidates/<?= e($profile['user_id']) ?>">
                                        <select name="status" onchange="this.form.submit()" class="rounded border border-slate-300 px-2 py-1 text-xs">
                                            <?php foreach (($statuses ?? []) as $sk => $sl): ?>
                                                <option value="<?= e($sk) ?>" <?= (string) $app['status'] === $sk ? 'selected' : '' ?>><?= e($sl) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                <?php else: ?>
                                    <span class="text-slate-500"><?= e($statuses[$app['status']] ?? $app['stage'] ?? $app['status']) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="<?= $card ?>">
                <h2 class="<?= $h2 ?>">Interviews</h2>
                <?php if (($canScheduleInterview ?? false) && $applications !== []): ?>
                    <form method="post" action="/candidates/<?= e($profile['user_id']) ?>/interviews" class="mb-4 flex flex-wrap items-center gap-2">
                        <?= csrf_field() ?>
                        <select name="application_id" class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
                            <?php foreach ($applications as $app): ?>
                                <option value="<?= e($app['id']) ?>"><?= e($app['job_title']) ?></option>
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
                                    <a href="/interviews/<?= e($iv['id']) ?>" class="rounded-md border border-slate-200 px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50">Transcript / report →</a>
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
        </div>

        <!-- ── Profile & CV ─────────────────────────────────────────── -->
        <div <?= $panel('profile', 'grid gap-4 lg:grid-cols-3') ?>>
            <div class="lg:col-span-2 <?= $card ?>">
                <h2 class="<?= $h2 ?>">Candidate details</h2>
                <?php if (! $hasDetails): ?>
                    <p class="text-sm text-slate-400">The candidate hasn’t completed their professional profile yet.</p>
                <?php else: ?>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <?php foreach (['skills' => 'Skills', 'languages' => 'Languages', 'education' => 'Education', 'certifications' => 'Certifications', 'location' => 'Location', 'availability' => 'Availability', 'current_salary' => 'Current salary', 'expected_salary' => 'Expected salary'] as $k => $label): ?>
                            <?php if ($dv($k) !== ''): ?>
                                <div<?= in_array($k, ['education', 'certifications', 'skills'], true) ? ' class="col-span-2"' : '' ?>>
                                    <dt class="<?= $lbl ?>"><?= e($label) ?></dt>
                                    <dd class="mt-0.5 text-slate-700"><?= in_array($k, ['current_salary', 'expected_salary'], true) ? e(number_format((float) $dv($k))) : nl2br(e($dv($k))) ?></dd>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
                <?php if ($canNote): ?>
                    <details class="mt-4 border-t border-slate-100 pt-3">
                        <summary class="cursor-pointer text-xs font-medium text-indigo-600 hover:underline">Paste CV text to auto-fill ↓</summary>
                        <form method="post" action="/candidates/<?= e($profile['user_id']) ?>/parse-cv" class="mt-2 space-y-2">
                            <?= csrf_field() ?>
                            <textarea name="cv_text" rows="5" placeholder="Paste the CV text here — email, phone, links, years of experience and skills are extracted automatically (existing values are kept)." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"></textarea>
                            <button class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">Extract fields</button>
                        </form>
                    </details>
                <?php endif; ?>
            </div>

            <div class="<?= $card ?>">
                <h2 class="<?= $h2 ?>">CVs &amp; files</h2>
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
        </div>

        <!-- ── Notes & Offers ───────────────────────────────────────── -->
        <div <?= $panel('engagement', 'grid gap-4 lg:grid-cols-2') ?>>
            <div class="<?= $card ?>">
                <h2 class="<?= $h2 ?>">Notes</h2>
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

            <div class="space-y-4">
                <div class="<?= $card ?>">
                    <h2 class="<?= $h2 ?>">Tags</h2>
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

                <div class="<?= $card ?>">
                    <h2 class="<?= $h2 ?>">Talent pools</h2>
                    <?php if (($candidatePools ?? []) === []): ?>
                        <p class="mb-2 text-sm text-slate-400">Not saved to any pool.</p>
                    <?php else: ?>
                        <div class="mb-3 flex flex-wrap gap-1.5">
                            <?php foreach ($candidatePools as $cp): ?>
                                <a href="/talent-pool/<?= e($cp['id']) ?>" class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100"><?= e($cp['name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (($canManageTalent ?? false) && ($talentPools ?? []) !== []): ?>
                        <form method="post" action="/talent-pool/add" class="flex gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="candidate_user_id" value="<?= e($profile['user_id']) ?>">
                            <input type="hidden" name="redirect_to" value="/candidates/<?= e($profile['user_id']) ?>">
                            <select name="pool_id" class="grow rounded-lg border border-slate-300 px-2 py-2 text-sm">
                                <?php foreach ($talentPools as $tp): ?>
                                    <option value="<?= e($tp['id']) ?>"><?= e($tp['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
                        </form>
                    <?php elseif (($canManageTalent ?? false)): ?>
                        <a href="/talent-pool" class="text-xs text-indigo-600 hover:underline">Create a pool first →</a>
                    <?php endif; ?>
                </div>

                <div class="<?= $card ?>">
                    <h2 class="<?= $h2 ?>">Offers</h2>
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
        </div>

        <!-- ── Timeline ─────────────────────────────────────────────── -->
        <div <?= $panel('timeline') ?>>
            <div class="<?= $card ?>">
                <h2 class="<?= $h2 ?>">Timeline <span class="text-xs font-normal text-slate-400">— this workspace only</span></h2>

                <?php if (! empty($canNote)): ?>
                    <form method="post" action="/candidates/<?= e($profile['user_id']) ?>/timeline" class="mb-5 rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                        <?= csrf_field() ?>
                        <div class="flex flex-wrap gap-2">
                            <select name="kind" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                <option value="update">Update</option>
                                <option value="call">Call</option>
                                <option value="message">Message</option>
                                <option value="meeting">Meeting</option>
                                <option value="note">Note</option>
                            </select>
                            <input type="datetime-local" name="occurred_at" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm text-slate-600">
                        </div>
                        <textarea name="body" required rows="2" placeholder="Log a call, message, meeting or update…" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                        <div class="mt-2 text-right">
                            <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-indigo-700">Add to timeline</button>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if (($timeline ?? []) === []): ?>
                    <p class="text-sm text-slate-400">No activity yet.</p>
                <?php else: ?>
                    <ol class="relative space-y-3 border-s border-slate-200 ps-5 text-sm">
                        <?php foreach ($timeline as $ev): ?>
                            <li class="relative">
                                <span class="absolute -start-[1.42rem] top-1.5 h-2 w-2 rounded-full bg-indigo-400"></span>
                                <div class="text-slate-700"><?= e($ev['label']) ?></div>
                                <div class="text-xs text-slate-400">
                                    <?= e($ev['type']) ?> · <?= e($ev['at']) ?> UTC<?php if (! empty($ev['by'])): ?> · by <?= e($ev['by']) ?><?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var root = document.getElementById('cand-profile');
    if (!root || root.__tabs) return;
    root.__tabs = true;
    var tabs = root.querySelectorAll('.wf-tab');
    var panels = root.querySelectorAll('[data-panel]');
    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            var name = t.getAttribute('data-tab');
            tabs.forEach(function (x) {
                x.classList.remove('border-indigo-600', 'text-indigo-600');
                x.classList.add('border-transparent', 'text-slate-500');
            });
            t.classList.add('border-indigo-600', 'text-indigo-600');
            t.classList.remove('border-transparent', 'text-slate-500');
            panels.forEach(function (p) { p.classList.toggle('hidden', p.getAttribute('data-panel') !== name); });
        });
    });
})();
</script>
