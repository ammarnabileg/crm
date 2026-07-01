<?php
/** @var array<string,mixed> $interview */
/** @var list<array<string,mixed>> $cvs */
/** @var string $applicationId */
/** @var string $workspaceName */
/** @var string|null $status */
?>
<div class="mx-auto max-w-2xl">
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-slate-900">Before we begin · <?= e($interview['job_title'] ?? 'AI Interview') ?></h1>
        <p class="mt-1 text-sm text-slate-500">Your interviewer reads your CV to ask relevant, personalised questions. Choose the CV you’d like us to use, or upload a new one — then start your interview.</p>
    </div>

    <?php if (! empty($status)): ?>
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800"><?= e((string) $status) ?></div>
    <?php endif; ?>

    <form method="post" action="/interview/<?= e($interview['id']) ?>/cv" enctype="multipart/form-data" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <?= csrf_field() ?>

        <?php if (! empty($cvs)): ?>
            <fieldset>
                <legend class="text-sm font-semibold text-slate-700">Use one of your CVs</legend>
                <div class="mt-3 space-y-2">
                    <?php foreach ($cvs as $i => $cv): ?>
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50">
                            <input type="radio" name="cv_file_id" value="<?= e((string) $cv['id']) ?>" <?= $i === 0 ? 'checked' : '' ?> class="text-indigo-600 focus:ring-indigo-500">
                            <span class="flex-1 text-sm text-slate-800"><?= e((string) ($cv['original_name'] ?? 'CV')) ?></span>
                            <?php if (! empty($cv['created_at'])): ?>
                                <span class="text-xs text-slate-400"><?= e(substr((string) $cv['created_at'], 0, 10)) ?></span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="flex items-center gap-3 text-xs uppercase tracking-wide text-slate-400">
                <span class="h-px flex-1 bg-slate-200"></span>or<span class="h-px flex-1 bg-slate-200"></span>
            </div>
        <?php endif; ?>

        <div>
            <label class="text-sm font-semibold text-slate-700" for="cv-upload">Upload a new CV</label>
            <p class="mb-2 text-xs text-slate-400">PDF or Word. Uploading a new file will use it for this interview and add it to your CV library.</p>
            <input id="cv-upload" type="file" name="cv" accept=".pdf,.doc,.docx" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
        </div>

        <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
            <a href="/my-applications/<?= e($applicationId) ?>" class="text-sm font-medium text-slate-500 hover:text-slate-700">Not now</a>
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Start interview</button>
        </div>
    </form>

    <p class="mt-3 text-center text-xs text-slate-400">This AI interview is advisory — a human always makes the final decision.</p>
</div>
