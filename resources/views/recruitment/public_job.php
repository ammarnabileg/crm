<?php
/** @var array<string,mixed> $job */
/** @var string $token */
/** @var bool $authenticated */
/** @var string|null $status */
/** @var string|null $error */
?>
<h1 class="mb-1 text-xl font-semibold text-slate-900"><?= e($job['title']) ?></h1>
<p class="mb-4 text-sm text-slate-500"><?= $job['location'] ? e($job['location']) . ' · ' : '' ?><?= e($job['employment_type'] ?: 'Full-time') ?></p>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

<div class="mb-6 whitespace-pre-line text-sm text-slate-600"><?= e($job['description'] ?: 'No description provided.') ?></div>

<?php if ($authenticated): ?>
    <form method="post" action="/jobs/public/<?= e($token) ?>/apply" class="space-y-3">
        <?= csrf_field() ?>
        <textarea name="cover_note" rows="3" placeholder="Add a short note (optional)…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"></textarea>
        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Apply now</button>
    </form>
<?php else: ?>
    <a href="/login" class="block w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-indigo-700">Sign in to apply</a>
    <p class="mt-3 text-center text-sm text-slate-500">No account? <a href="/register" class="font-medium text-indigo-600 hover:underline">Create one</a></p>
<?php endif; ?>
