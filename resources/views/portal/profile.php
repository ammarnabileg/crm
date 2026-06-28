<?php
/** @var array<string,mixed>|null $user */
/** @var string $workspaceName */
/** @var string|null $status */
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
        <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Save changes</button>
    </form>
</div>
