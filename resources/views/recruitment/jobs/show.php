<?php
/** @var array<string,mixed> $job */
/** @var list<array<string,mixed>> $stages */
/** @var bool $canPublish */
/** @var bool $canEdit */
/** @var bool $canViewPipeline */
/** @var bool $canInvite */
/** @var list<array<string,mixed>> $invitations */
/** @var list<array<string,mixed>> $questions */
/** @var list<array<string,mixed>> $criteria */
/** @var list<array<string,mixed>> $avatars */
/** @var array<string,mixed>|null $linkedAvatar */
/** @var array<string,bool> $aiStatus */
/** @var string|null $newLink */
/** @var string|null $status */

$cfgChip = static fn (string $label, string $value): string =>
    '<div class="rounded-lg bg-slate-50 px-3 py-2"><div class="text-xs text-slate-400">' . e($label) . '</div><div class="text-sm font-medium text-slate-700">' . e($value) . '</div></div>';
$onOff = static fn ($v): string => (int) $v === 1 ? 'On' : 'Off';
$orDash = static fn ($v): string => ($v === null || $v === '') ? '—' : (string) $v;
?>
<div class="mb-6 flex items-start justify-between">
    <div>
        <a href="/jobs" class="text-sm text-indigo-600 hover:underline">&larr; Jobs</a>
        <h1 class="mt-1 text-2xl font-semibold text-slate-900"><?= e($job['title']) ?></h1>
        <p class="mt-1 text-sm text-slate-500">
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"><?= e($job['status']) ?></span>
            <?php if (! empty($job['seniority'])): ?>· <span class="capitalize"><?= e($job['seniority']) ?></span><?php endif; ?>
            <?= $job['location'] ? '· ' . e($job['location']) : '' ?>
            <?php if (! empty($job['salary_min']) || ! empty($job['salary_max'])): ?>
                · <?= e(number_format((int) ($job['salary_min'] ?? 0))) ?>–<?= e(number_format((int) ($job['salary_max'] ?? 0))) ?> <?= e($job['currency'] ?? 'USD') ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex gap-2">
        <?php if ($canViewPipeline && $job['status'] !== 'draft'): ?>
            <a href="/jobs/<?= e($job['id']) ?>/pipeline" class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Pipeline</a>
        <?php endif; ?>
        <?php if ($canEdit): ?>
            <a href="/jobs/<?= e($job['id']) ?>/edit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Edit</a>
        <?php endif; ?>
        <?php if ($canPublish || $canEdit): ?>
            <form method="post" action="/jobs/<?= e($job['id']) ?>/clone"><?= csrf_field() ?>
                <button class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Clone</button>
            </form>
        <?php endif; ?>
        <?php if ($canPublish && $job['status'] === 'draft'): ?>
            <form method="post" action="/jobs/<?= e($job['id']) ?>/publish"><?= csrf_field() ?>
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Publish</button>
            </form>
        <?php endif; ?>
        <?php if ($canEdit): ?>
            <form method="post" action="/jobs/<?= e($job['id']) ?>/archive" onsubmit="return confirm('Archive this job?');"><?= csrf_field() ?>
                <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">Archive</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<?php if ($job['status'] === 'published'): ?>
    <div class="mb-4 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-700">
        Public link: <a class="font-mono underline" href="/jobs/public/<?= e($job['public_token']) ?>">/jobs/public/<?= e($job['public_token']) ?></a>
    </div>
<?php endif; ?>

<?php if (! empty($newLink)): ?>
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
        <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Interview link generated — send it to the candidate (valid 14 days, single-use)</div>
        <code class="mt-1 block break-all font-mono text-sm text-amber-900"><?= e($newLink) ?></code>
    </div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-2 text-sm font-semibold text-slate-900">Description</h2>
        <p class="whitespace-pre-line text-sm text-slate-600"><?= e($job['description'] ?: 'No description yet.') ?></p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Pipeline stages</h2>
        <?php if ($stages === []): ?>
            <p class="text-sm text-slate-400">Stages are created when you publish.</p>
        <?php else: ?>
            <ol class="space-y-1 text-sm text-slate-600">
                <?php foreach ($stages as $s): ?>
                    <li class="rounded-md bg-slate-50 px-3 py-1.5"><?= e($s['name']) ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</div>

<!-- Hiring configuration + AI interviewer avatar -->
<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900">Hiring configuration</h2>
            <?php if ($canEdit): ?><a href="/jobs/<?= e($job['id']) ?>/edit" class="text-xs font-medium text-indigo-600 hover:underline">Edit configuration</a><?php endif; ?>
        </div>
        <div class="grid gap-2 sm:grid-cols-3">
            <?= $cfgChip('AI screening', $onOff($job['ai_screening_enabled'] ?? 1)) ?>
            <?= $cfgChip('Interview', $onOff($job['interview_required'] ?? 1) === 'On' ? 'Required' : 'Optional') ?>
            <?= $cfgChip('Type', ucfirst((string) ($job['interview_type'] ?? 'text'))) ?>
            <?= $cfgChip('Passing score', $orDash($job['passing_score'] ?? null)) ?>
            <?= $cfgChip('Auto-reject below', $orDash($job['auto_reject_score'] ?? null)) ?>
            <?= $cfgChip('Start mode', ucfirst((string) ($job['interview_start_mode'] ?? 'choice'))) ?>
            <?= $cfgChip('Duration (min)', $orDash($job['interview_duration_minutes'] ?? null) === '—' ? '20 (default)' : (string) $job['interview_duration_minutes']) ?>
            <?= $cfgChip('Questions limit', $orDash($job['questions_limit'] ?? null) === '—' ? '12 (default)' : (string) $job['questions_limit']) ?>
            <?= $cfgChip('Max attempts', (string) ($job['max_attempts'] ?? 1)) ?>
            <?= $cfgChip('Experience (yrs)', $orDash($job['experience_min'] ?? null) . '–' . $orDash($job['experience_max'] ?? null)) ?>
            <?= $cfgChip('Interview expires', $orDash($job['interview_expiration_days'] ?? null) === '—' ? '—' : $job['interview_expiration_days'] . ' days') ?>
            <?= $cfgChip('Deadline', $orDash(! empty($job['deadline_at']) ? substr((string) $job['deadline_at'], 0, 16) . ' UTC' : null)) ?>
        </div>
        <?php if (! empty($job['screening_keywords'])): ?>
            <div class="mt-3"><span class="text-xs text-slate-400">Screening keywords:</span> <span class="text-sm text-slate-700"><?= e((string) $job['screening_keywords']) ?></span></div>
        <?php endif; ?>
        <?php if (! empty($job['required_skills'])): ?>
            <div class="mt-1"><span class="text-xs text-slate-400">Required skills:</span> <span class="text-sm text-slate-700"><?= e((string) $job['required_skills']) ?></span></div>
        <?php endif; ?>
    </div>

    <!-- AI interviewer avatar (spec #3) -->
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-900">AI interviewer avatar</h2>
        <?php if ($linkedAvatar !== null): ?>
            <div class="flex items-center gap-3">
                <?php if (! empty($linkedAvatar['image_url'])): ?>
                    <img src="<?= e((string) $linkedAvatar['image_url']) ?>" alt="" class="h-12 w-12 rounded-full object-cover">
                <?php else: ?>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 text-lg font-semibold text-indigo-600"><?= e(strtoupper(substr((string) $linkedAvatar['name'], 0, 1))) ?></div>
                <?php endif; ?>
                <div>
                    <div class="text-sm font-semibold text-slate-800"><?= e((string) $linkedAvatar['name']) ?></div>
                    <div class="text-xs capitalize text-slate-400"><?= e((string) ($linkedAvatar['persona'] ?? 'professional')) ?></div>
                </div>
            </div>
            <?php if ((string) ($job['interview_type'] ?? '') === 'avatar' && empty($aiStatus['video'])): ?>
                <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">HeyGen isn't configured — the interview will automatically fall back to text/voice.</p>
            <?php endif; ?>
            <?php if ($canEdit): ?>
                <div class="mt-4 flex items-center gap-2">
                    <a href="/avatars/<?= e((string) $linkedAvatar['id']) ?>/preview" target="_blank" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">Preview</a>
                    <form method="post" action="/jobs/<?= e($job['id']) ?>/avatar/remove"><?= csrf_field() ?>
                        <button class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50">✕ Remove</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-sm text-slate-500">Using the default strong-HR persona.</p>
            <?php if ($canEdit && $avatars !== []): ?>
                <form method="post" action="/jobs/<?= e($job['id']) ?>/avatar" class="mt-3 flex gap-2">
                    <?= csrf_field() ?>
                    <select name="avatar_id" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                        <?php foreach ($avatars as $av): ?><option value="<?= e((string) $av['id']) ?>"><?= e((string) $av['name']) ?></option><?php endforeach; ?>
                    </select>
                    <button class="whitespace-nowrap rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700">Link</button>
                </form>
            <?php elseif ($canEdit): ?>
                <p class="mt-2 text-xs text-slate-400">Create an avatar under <a href="/avatars" class="text-indigo-600 hover:underline">Avatars</a> to link one here.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <!-- Question bank (spec #4) -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Interview question bank</h2>
            <p class="text-xs text-slate-400">Asked by the AI interviewer, in order. Falls back to defaults if empty.</p>
        </div>
        <?php if ($questions === []): ?>
            <p class="px-5 py-4 text-sm text-slate-400">No questions yet — the AI uses sensible defaults.</p>
        <?php else: ?>
            <ol class="divide-y divide-slate-100">
                <?php foreach ($questions as $i => $q): ?>
                    <li class="flex items-start justify-between gap-3 px-5 py-2.5 text-sm">
                        <span class="text-slate-700"><span class="text-slate-400"><?= $i + 1 ?>.</span> <?= e($q['text']) ?></span>
                        <?php if ($canEdit): ?>
                            <form method="post" action="/jobs/<?= e($job['id']) ?>/questions/<?= e($q['id']) ?>/delete"><?= csrf_field() ?><button class="text-xs text-rose-500 hover:text-rose-700">remove</button></form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
        <?php if ($canEdit): ?>
            <form method="post" action="/jobs/<?= e($job['id']) ?>/questions" class="flex gap-2 border-t border-slate-100 px-5 py-3">
                <?= csrf_field() ?>
                <input name="text" required placeholder="Add a question…" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                <button class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700">Add</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Evaluation criteria / rubric (spec #2) -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Evaluation criteria (rubric)</h2>
            <p class="text-xs text-slate-400">Weighted dimensions interviewers score against.</p>
        </div>
        <?php if ($criteria === []): ?>
            <p class="px-5 py-4 text-sm text-slate-400">No criteria defined yet.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($criteria as $c): ?>
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                        <span class="text-slate-700"><?= e($c['label']) ?></span>
                        <span class="flex items-center gap-2">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">weight <?= e($c['weight']) ?></span>
                            <?php if ($canEdit): ?>
                                <form method="post" action="/jobs/<?= e($job['id']) ?>/criteria/<?= e($c['id']) ?>/delete"><?= csrf_field() ?><button class="text-xs text-rose-500 hover:text-rose-700">remove</button></form>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($canEdit): ?>
            <form method="post" action="/jobs/<?= e($job['id']) ?>/criteria" class="flex gap-2 border-t border-slate-100 px-5 py-3">
                <?= csrf_field() ?>
                <input name="label" required placeholder="e.g. System design" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                <input name="weight" type="number" min="1" max="100" value="10" class="w-20 rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                <button class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700">Add</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($canInvite ?? false): ?>
    <div class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">AI interview links</h2>
            <form method="post" action="/jobs/<?= e($job['id']) ?>/interview-link" class="flex items-center gap-2">
                <?= csrf_field() ?>
                <input name="candidate_email" type="email" placeholder="candidate email (optional)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                <button class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700">Generate link</button>
            </form>
        </div>
        <?php if (($invitations ?? []) === []): ?>
            <p class="px-5 py-6 text-sm text-slate-400">No links yet. Generate one to invite a candidate to the AI interview.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($invitations as $inv): ?>
                    <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                        <div>
                            <span class="font-mono text-xs text-slate-500">/interview-link/<?= e(substr((string) $inv['token'], 0, 10)) ?>…</span>
                            <?php if (! empty($inv['candidate_email'])): ?><span class="text-slate-400">· <?= e($inv['candidate_email']) ?></span><?php endif; ?>
                        </div>
                        <div class="text-right">
                            <?php $sc = (string) $inv['status'] === 'completed' ? 'bg-emerald-50 text-emerald-700' : ((string) $inv['status'] === 'pending' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-500'); ?>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $sc ?>"><?= e($inv['status']) ?></span>
                            <div class="mt-0.5 text-xs text-slate-400">expires <?= e($inv['expires_at']) ?> UTC</div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>
