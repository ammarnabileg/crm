<?php
/** @var array<string,mixed> $profile */
/** @var string|null $status */
/** @var string|null $error */
$field = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Edit profile</h1>
    <p class="mt-1 text-sm text-slate-500">Your name, email and password. These apply to your account across all workspaces.</p>
</div>

<?php if ($status): ?><div class="mb-4 max-w-xl rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 max-w-xl rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

<form method="post" action="/account/profile" class="max-w-xl space-y-6">
    <?= csrf_field() ?>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-slate-900">Details</h2>
        <div class="space-y-4">
            <div><label class="mb-1 block text-sm font-medium text-slate-700">Full name</label><input name="name" value="<?= e($profile['name'] ?? '') ?>" required class="<?= $field ?>"></div>
            <div><label class="mb-1 block text-sm font-medium text-slate-700">Email</label><input name="email" type="email" value="<?= e($profile['email'] ?? '') ?>" required class="<?= $field ?>"></div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-1 text-sm font-semibold text-slate-900">Change password</h2>
        <p class="mb-4 text-xs text-slate-400">Leave blank to keep your current password.</p>
        <div class="space-y-4">
            <div><label class="mb-1 block text-sm font-medium text-slate-700">Current password</label><input name="current_password" type="password" autocomplete="current-password" class="<?= $field ?>"></div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div><label class="mb-1 block text-sm font-medium text-slate-700">New password</label><input name="new_password" type="password" autocomplete="new-password" class="<?= $field ?>"></div>
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Confirm new password</label><input name="new_password_confirmation" type="password" autocomplete="new-password" class="<?= $field ?>"></div>
            </div>
        </div>
    </div>

    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save changes</button>
</form>
