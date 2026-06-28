<?php
/** @var array<string,mixed>|null $user */
/** @var list<array<string,mixed>> $cvs */
/** @var array<string,mixed> $details */
/** @var string $workspaceName */
/** @var string|null $status */
$d = static fn (string $k): string => (string) ($details[$k] ?? '');
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">My profile</h1>
    <p class="mt-1 text-sm text-slate-500">Your personal details. These apply across every workspace you’re a candidate in.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <form method="post" action="/portal/profile" class="space-y-4">
        <?= csrf_field() ?>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Full name</label>
            <input name="name" required value="<?= e($user['name'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
            <input value="<?= e($user['email'] ?? '') ?>" disabled class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-400">
            <p class="mt-1 text-xs text-slate-400">Email can’t be changed here.</p>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Phone</label>
            <input name="phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="+20 1xx xxx xxxx" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Years of experience</label>
                <input name="years_experience" type="number" min="0" max="60" value="<?= e($user['years_experience'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Target salary</label>
                <input name="target_salary" type="number" min="0" value="<?= e($user['target_salary'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Current salary</label>
                <input name="current_salary" type="number" min="0" value="<?= e($d('current_salary')) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Availability</label>
                <input name="availability" value="<?= e($d('availability')) ?>" placeholder="e.g. Immediate, 1 month" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Location</label>
            <input name="location" value="<?= e($d('location')) ?>" placeholder="City, Country" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Skills <span class="text-xs text-slate-400">(comma-separated)</span></label>
            <input name="skills" value="<?= e($d('skills')) ?>" placeholder="PHP, MySQL, System design" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Languages</label>
            <input name="languages" value="<?= e($d('languages')) ?>" placeholder="Arabic (native), English (fluent)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Education</label>
            <textarea name="education" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="Degrees, institutions, years"><?= e($d('education')) ?></textarea>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Certifications</label>
            <textarea name="certifications" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="Certificates, licenses"><?= e($d('certifications')) ?></textarea>
        </div>
        <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Save changes</button>
    </form>
</div>

<div class="mt-6 max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="mb-1 text-sm font-semibold text-slate-900">My CVs</h2>
    <p class="mb-3 text-xs text-slate-400">Stored for <?= e($workspaceName) ?>. Upload here, then attach when you apply.</p>
    <?php if (($cvs ?? []) === []): ?>
        <p class="mb-3 text-sm text-slate-400">No CVs uploaded yet.</p>
    <?php else: ?>
        <ul class="mb-3 divide-y divide-slate-100">
            <?php foreach ($cvs as $f): ?>
                <li class="flex items-center justify-between py-2 text-sm">
                    <a href="/files/<?= e($f['id']) ?>/download" class="text-indigo-600 hover:underline"><?= e($f['original_name']) ?></a>
                    <span class="text-xs text-slate-400"><?= e(number_format(((int) ($f['size_bytes'] ?? 0)) / 1024, 0)) ?> KB</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <form method="post" action="/portal/cv" enctype="multipart/form-data" class="flex items-center gap-2">
        <?= csrf_field() ?>
        <input name="cv" type="file" accept=".pdf,.doc,.docx" required class="block w-full text-xs text-slate-500 file:mr-2 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs">
        <button class="shrink-0 rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700">Upload</button>
    </form>
</div>
