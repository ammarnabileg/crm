<?php
/**
 * Shared per-job hiring configuration fieldset (create + edit). Reads $job with
 * sensible defaults so it works for a brand-new job ($job = []) too.
 *
 * @var array<string,mixed> $job
 * @var list<array<string,mixed>> $avatars   active avatars (pickable)
 * @var list<array<string,mixed>> $stages    pipeline stages (for auto-advance)
 * @var array<string,bool> $aiStatus         provider status: interviews, video, ...
 */
$jval = static fn (string $k, $d = '') => $job[$k] ?? $d;
$jon = static fn (string $k, bool $on): bool => (isset($job[$k]) ? (int) $job[$k] : ($on ? 1 : 0)) === 1;
$jsel = static fn (string $k, string $v, string $d): string => (string) ($job[$k] ?? $d) === $v ? 'selected' : '';
$deadlineVal = str_replace(' ', 'T', substr((string) ($job['deadline_at'] ?? ''), 0, 16));
$aiReady = ! empty($aiStatus['interviews']);
$videoReady = ! empty($aiStatus['video']);
$inputCls = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
?>
<fieldset class="space-y-5 rounded-xl border border-slate-200 bg-slate-50/60 p-5">
    <legend class="px-2 text-sm font-semibold text-slate-900">Hiring automation &amp; AI configuration</legend>

    <!-- Provider status -->
    <div class="flex flex-wrap gap-2 text-xs">
        <span class="rounded-full px-2.5 py-0.5 font-medium <?= $aiReady ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-500' ?>">
            AI interviews: <?= $aiReady ? 'ready' : 'no OpenAI key' ?>
        </span>
        <span class="rounded-full px-2.5 py-0.5 font-medium <?= $videoReady ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-500' ?>">
            Avatar/video: <?= $videoReady ? 'ready' : 'no HeyGen key' ?>
        </span>
        <span class="text-slate-400">Uses your workspace AI keys only — the platform is never billed.</span>
    </div>

    <!-- Toggles -->
    <div class="grid gap-3 sm:grid-cols-2">
        <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5">
            <input type="checkbox" name="ai_screening_enabled" value="1" <?= $jon('ai_screening_enabled', true) ? 'checked' : '' ?> class="h-4 w-4 rounded text-indigo-600 focus:ring-indigo-500">
            <span><span class="block text-sm font-medium text-slate-700">Enable AI screening</span><span class="block text-xs text-slate-400">Off = applications accepted, no AI interview.</span></span>
        </label>
        <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5">
            <input type="checkbox" name="interview_required" value="1" <?= $jon('interview_required', true) ? 'checked' : '' ?> class="h-4 w-4 rounded text-indigo-600 focus:ring-indigo-500">
            <span><span class="block text-sm font-medium text-slate-700">Interview required</span><span class="block text-xs text-slate-400">Candidates must complete the interview to qualify.</span></span>
        </label>
    </div>

    <!-- Interview behaviour -->
    <div class="grid gap-3 sm:grid-cols-3">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Interview type</label>
            <select name="interview_type" class="<?= $inputCls ?>">
                <option value="text" <?= $jsel('interview_type', 'text', 'text') ?>>Text</option>
                <option value="voice" <?= $jsel('interview_type', 'voice', 'text') ?>>Voice</option>
                <option value="avatar" <?= $jsel('interview_type', 'avatar', 'text') ?>>Avatar (video)</option>
            </select>
            <p class="mt-1 text-xs text-slate-400">Avatar falls back to text/voice if no avatar/HeyGen is available.</p>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Start mode</label>
            <select name="interview_start_mode" class="<?= $inputCls ?>">
                <option value="choice" <?= $jsel('interview_start_mode', 'choice', 'choice') ?>>Let candidate choose (now / later)</option>
                <option value="immediate" <?= $jsel('interview_start_mode', 'immediate', 'choice') ?>>Start immediately</option>
                <option value="later" <?= $jsel('interview_start_mode', 'later', 'choice') ?>>Schedule for later</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Linked avatar</label>
            <div class="flex gap-2">
                <select name="avatar_id" class="<?= $inputCls ?>">
                    <option value="">— Default AI persona —</option>
                    <?php foreach ($avatars as $av): ?>
                        <option value="<?= e((string) $av['id']) ?>" <?= (string) ($job['avatar_id'] ?? '') === (string) $av['id'] ? 'selected' : '' ?>><?= e((string) $av['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (! empty($job['avatar_id'])): ?>
                    <a href="/avatars/<?= e((string) $job['avatar_id']) ?>/preview" target="_blank" class="whitespace-nowrap rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-white">Preview</a>
                <?php endif; ?>
            </div>
            <?php if ($avatars === []): ?><p class="mt-1 text-xs text-slate-400">No avatars yet — create one under Avatars.</p><?php endif; ?>
        </div>
    </div>

    <!-- Matching inputs -->
    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Screening keywords</label>
            <textarea name="screening_keywords" rows="2" placeholder="php, mysql, leadership" class="<?= $inputCls ?>"><?= e((string) $jval('screening_keywords')) ?></textarea>
            <p class="mt-1 text-xs text-slate-400">Comma/line separated. If set, the AI interview only runs when an applicant matches — saving AI credits.</p>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Required skills</label>
            <textarea name="required_skills" rows="2" placeholder="REST APIs, Docker, team leadership" class="<?= $inputCls ?>"><?= e((string) $jval('required_skills')) ?></textarea>
            <p class="mt-1 text-xs text-slate-400">Used for the AI skills-match score.</p>
        </div>
    </div>

    <!-- Experience + scoring -->
    <div class="grid gap-3 sm:grid-cols-4">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Min experience (yrs)</label>
            <input type="number" min="0" name="experience_min" value="<?= e((string) $jval('experience_min')) ?>" class="<?= $inputCls ?>">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Max experience (yrs)</label>
            <input type="number" min="0" name="experience_max" value="<?= e((string) $jval('experience_max')) ?>" class="<?= $inputCls ?>">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Passing score</label>
            <input type="number" min="0" max="100" name="passing_score" value="<?= e((string) $jval('passing_score')) ?>" placeholder="70" class="<?= $inputCls ?>">
            <p class="mt-1 text-xs text-slate-400">≥ auto-qualifies.</p>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Auto-reject below</label>
            <input type="number" min="0" max="100" name="auto_reject_score" value="<?= e((string) $jval('auto_reject_score')) ?>" placeholder="40" class="<?= $inputCls ?>">
            <p class="mt-1 text-xs text-slate-400">&lt; auto-rejects.</p>
        </div>
    </div>

    <!-- Pipeline + limits -->
    <div class="grid gap-3 sm:grid-cols-3">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Auto-move qualified to</label>
            <select name="auto_advance_stage_id" class="<?= $inputCls ?>">
                <option value="">— Don't auto-move —</option>
                <?php foreach ($stages as $st): ?>
                    <option value="<?= e((string) $st['id']) ?>" <?= (string) ($job['auto_advance_stage_id'] ?? '') === (string) $st['id'] ? 'selected' : '' ?>><?= e((string) $st['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($stages === []): ?><p class="mt-1 text-xs text-slate-400">Stages appear after you publish.</p><?php endif; ?>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Max attempts</label>
            <input type="number" min="1" max="10" name="max_attempts" value="<?= e((string) ($job['max_attempts'] ?? 1)) ?>" class="<?= $inputCls ?>">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Interview expiration (days)</label>
            <input type="number" min="1" name="interview_expiration_days" value="<?= e((string) $jval('interview_expiration_days')) ?>" placeholder="14" class="<?= $inputCls ?>">
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Interview duration (min)</label>
            <input type="number" min="1" max="180" name="interview_duration_minutes" value="<?= e((string) $jval('interview_duration_minutes')) ?>" placeholder="20" class="<?= $inputCls ?>">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Questions limit</label>
            <input type="number" min="1" max="30" name="questions_limit" value="<?= e((string) $jval('questions_limit')) ?>" placeholder="12" class="<?= $inputCls ?>">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Application deadline</label>
            <input type="datetime-local" name="deadline_at" value="<?= e($deadlineVal) ?>" class="<?= $inputCls ?>">
        </div>
    </div>
</fieldset>
