<?php /** @var string $code */ /** @var string|null $error */ ?>
<h1 class="mb-1 text-xl font-semibold text-slate-900">Join workspace</h1>
<p class="mb-6 text-sm text-slate-500">You've been invited to collaborate. Accept to join.</p>

<?php if ($error): ?>
    <div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="/invitations/<?= e($code) ?>">
    <?= csrf_field() ?>
    <p class="mb-4 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">Invitation code: <span class="font-mono font-semibold"><?= e($code) ?></span></p>
    <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Accept invitation</button>
</form>
<p class="mt-6 text-center text-sm text-slate-500"><a href="/dashboard" class="font-medium text-indigo-600 hover:underline">Back to dashboard</a></p>
