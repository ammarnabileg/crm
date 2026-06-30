<?php
/** @var array<string,mixed> $job */
/** @var string|null $error */
/** @var list<array<string,mixed>> $avatars */
/** @var list<array<string,mixed>> $stages */
/** @var array<string,bool> $aiStatus */
$sel = static fn (string $v): string => (string) ($job['seniority'] ?? '') === $v ? 'selected' : '';
?>
<div class="mb-6">
    <a href="/jobs/<?= e($job['id']) ?>" class="text-sm text-indigo-600 hover:underline">&larr; Back to job</a>
    <h1 class="mt-1 text-2xl font-semibold text-slate-900">Edit job</h1>
</div>

<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

<form method="post" action="/jobs/<?= e($job['id']) ?>/edit" class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
    <?= csrf_field() ?>
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Title</label>
        <input name="title" required value="<?= e($job['title']) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Seniority</label>
            <select name="seniority" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                <?php foreach (['intern' => 'Intern', 'junior' => 'Junior', 'mid' => 'Mid', 'senior' => 'Senior', 'lead' => 'Lead', 'manager' => 'Manager', 'director' => 'Director', 'executive' => 'Executive'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $sel($v) ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Employment type</label>
            <input name="employment_type" value="<?= e($job['employment_type'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        </div>
    </div>
    <div class="grid grid-cols-4 gap-3">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Location</label>
            <input name="location" value="<?= e($job['location'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Salary min</label>
            <input name="salary_min" type="number" min="0" value="<?= e($job['salary_min'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Salary max</label>
            <input name="salary_max" type="number" min="0" value="<?= e($job['salary_max'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Currency</label>
            <input name="currency" value="<?= e($job['currency'] ?? 'USD') ?>" maxlength="8" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        </div>
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
        <textarea name="description" rows="6" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"><?= e($job['description'] ?? '') ?></textarea>
    </div>

    <?php require __DIR__ . '/_config_form.php'; ?>

    <div class="flex gap-2">
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save changes</button>
        <a href="/jobs/<?= e($job['id']) ?>" class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
    </div>
</form>
