<?php
/** @var array<string,string> $values */
/** @var string|null $status */
$v = static fn (string $k): string => (string) ($values[$k] ?? '');
$field = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Platform settings</h1>
    <p class="mt-1 text-sm text-slate-500">Platform-wide configuration. The support contact appears on suspended workspaces.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<form method="post" action="/admin/settings" class="max-w-2xl space-y-6">
    <?= csrf_field() ?>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-slate-900">Platform</h2>
        <div><label class="mb-1 block text-sm font-medium text-slate-700">Platform name</label><input name="platform_name" value="<?= e($v('platform.name')) ?>" class="<?= $field ?>" placeholder="HaHireAI"></div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-1 text-sm font-semibold text-slate-900">Support contact</h2>
        <p class="mb-4 text-xs text-slate-400">Shown to members of any suspended workspace so they can reach you.</p>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Support email</label><input name="support_email" type="email" value="<?= e($v('support.email')) ?>" class="<?= $field ?>" placeholder="support@hahire.ai"></div>
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Support phone</label><input name="support_phone" value="<?= e($v('support.phone')) ?>" class="<?= $field ?>" placeholder="+20 …"></div>
            </div>
            <div><label class="mb-1 block text-sm font-medium text-slate-700">Support URL (help desk / contact form)</label><input name="support_url" value="<?= e($v('support.url')) ?>" class="<?= $field ?>" placeholder="https://help.hahire.ai"></div>
            <div><label class="mb-1 block text-sm font-medium text-slate-700">Message on suspended workspaces</label><textarea name="support_message" rows="2" class="<?= $field ?>" placeholder="Your subscription has ended. Contact us to reactivate."><?= e($v('support.message')) ?></textarea></div>
        </div>
    </div>

    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save settings</button>
</form>
