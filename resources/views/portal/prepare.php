<?php
/** @var array<string,mixed> $job */
/** @var array<string,string> $socialFields */
/** @var list<array<string,mixed>> $savedLinks */
/** @var list<array<string,mixed>> $resumes */
/** @var string $workspaceName */
/** @var string|null $status */

// Auto-fill: map a saved link to each named field (portfolio ↔ website).
$byPlatform = [];
foreach ($savedLinks as $l) {
    $byPlatform[(string) $l['platform']] = (string) $l['url'];
}
$prefill = static function (string $field) use ($byPlatform): string {
    $platform = $field === 'portfolio' ? 'website' : $field;

    return $byPlatform[$platform] ?? '';
};
$input = 'block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
?>
<div class="mx-auto max-w-2xl">
    <a href="/open-jobs" class="text-xs text-slate-400 hover:text-slate-600">← Back to jobs</a>
    <div class="mt-2 mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Prepare your application</h1>
        <p class="mt-1 text-sm text-slate-500">
            For <span class="font-medium text-slate-700"><?= e((string) $job['title']) ?></span> at <?= e($workspaceName) ?>.
        </p>
    </div>

    <?php if ($status): ?><div class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700"><?= e($status) ?></div><?php endif; ?>

    <div class="mb-5 rounded-xl border border-indigo-100 bg-indigo-50/60 px-4 py-3 text-xs text-indigo-700">
        After you continue, a quick automated <strong>First Impression review</strong> checks how well your CV and profiles
        match this role. It uses <strong>no AI</strong> and costs you nothing. Your application is always saved either way.
    </div>

    <form method="post" action="/open-jobs/<?= e((string) $job['id']) ?>/apply" enctype="multipart/form-data" class="space-y-6">
        <?= csrf_field() ?>

        <!-- Résumé (mandatory) -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">Résumé <span class="text-rose-500">*</span></h2>
            <p class="mt-0.5 text-xs text-slate-500">Choose a CV from your library or upload a new one (PDF, Word or text).</p>

            <?php if ($resumes !== []): ?>
                <div class="mt-3 space-y-2">
                    <?php foreach ($resumes as $i => $r): ?>
                        <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50">
                            <input type="radio" name="resume_id" value="<?= e((string) $r['id']) ?>" <?= $i === 0 ? 'checked' : '' ?> class="text-indigo-600">
                            <span class="min-w-0 grow truncate text-slate-700"><?= e((string) $r['original_name']) ?></span>
                            <span class="shrink-0 text-xs text-slate-400"><?= e(date('M Y', strtotime((string) $r['created_at']))) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="mt-3 text-xs font-medium text-slate-500">…or upload a new one</p>
            <?php endif; ?>

            <input name="cv" type="file" accept=".pdf,.doc,.docx,.txt,.rtf,.odt" class="mt-2 block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200">
        </section>

        <!-- Social profiles (optional) -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">Social profiles <span class="text-xs font-normal text-slate-400">(optional)</span></h2>
            <p class="mt-0.5 text-xs text-slate-500">We use public information only. Leaving these blank never lowers your score.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <?php foreach ($socialFields as $key => $label): ?>
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-600"><?= e($label) ?></span>
                        <input name="social_<?= e($key) ?>" value="<?= e($prefill($key)) ?>" placeholder="https://…" class="<?= $input ?>">
                    </label>
                <?php endforeach; ?>
            </div>
            <label class="mt-3 block">
                <span class="mb-1 block text-xs font-medium text-slate-600">Other links</span>
                <input name="social_extra" placeholder="Any other links, separated by spaces or commas" class="<?= $input ?>">
            </label>
        </section>

        <!-- Availability + note -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-slate-600">When can you start?</span>
                <input name="available_from" maxlength="255" placeholder="e.g. Immediately, 2 weeks, 1 month" class="<?= $input ?>">
            </label>
            <label class="mt-3 block">
                <span class="mb-1 block text-xs font-medium text-slate-600">Cover note <span class="text-slate-400">(optional)</span></span>
                <textarea name="cover_note" rows="3" class="<?= $input ?>" placeholder="A few lines on why you're a great fit…"></textarea>
            </label>
        </section>

        <div class="flex items-center justify-end gap-3">
            <a href="/open-jobs" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancel</a>
            <button class="rounded-lg bg-indigo-600 px-6 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Continue</button>
        </div>
    </form>
</div>
