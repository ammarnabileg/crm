<?php
/** @var bool $enabled */
/** @var string $message */
/** @var string $allowIps */
/** @var bool $canManage */
/** @var string $yourIp */
/** @var string|null $status */
?>
<div class="mb-6">
    <a href="/settings" class="text-xs text-slate-400 hover:text-slate-600">← Settings</a>
    <h1 class="mt-1 text-2xl font-semibold text-slate-900">Maintenance mode</h1>
    <p class="mt-1 text-sm text-slate-500">When on, the workspace pauses for everyone except admins and allow-listed IPs.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="max-w-2xl space-y-6">
    <div class="rounded-2xl border <?= $enabled ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-white' ?> p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Current status</h2>
                <p class="mt-1 text-sm <?= $enabled ? 'text-amber-800' : 'text-slate-500' ?>"><?= $enabled ? '⚠ Maintenance mode is ON — only admins and allow-listed IPs can use this workspace.' : 'Maintenance mode is off — the workspace is live.' ?></p>
            </div>
            <span class="rounded-full px-3 py-1 text-sm font-medium <?= $enabled ? 'bg-amber-100 text-amber-800' : 'bg-emerald-50 text-emerald-700' ?>"><?= $enabled ? 'ON' : 'OFF' ?></span>
        </div>
    </div>

    <?php if ($canManage): ?>
        <form method="post" action="/settings/maintenance/enable" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <?= csrf_field() ?>
            <h2 class="mb-4 text-sm font-semibold text-slate-900"><?= $enabled ? 'Update maintenance' : 'Enable maintenance' ?></h2>
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Message shown to members (optional)</label>
                    <input name="message" value="<?= e($message) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="We'll be back shortly.">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Allow these IPs through (optional)</label>
                    <textarea name="allow_ips" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="One or more IPs, comma or space separated"><?= e($allowIps) ?></textarea>
                    <p class="mt-1 text-xs text-slate-400">Your current IP is <span class="font-mono text-slate-600"><?= e($yourIp !== '' ? $yourIp : 'unknown') ?></span>. Invalid entries are ignored.</p>
                </div>
            </div>
            <button class="mt-5 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700"><?= $enabled ? 'Save & keep enabled' : 'Enable maintenance' ?></button>
        </form>

        <?php if ($enabled): ?>
            <form method="post" action="/settings/maintenance/disable" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <?= csrf_field() ?>
                <h2 class="mb-1 text-sm font-semibold text-slate-900">Disable</h2>
                <p class="mb-3 text-xs text-slate-400">Bring the workspace back online for everyone.</p>
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Disable maintenance</button>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <p class="text-sm text-slate-400">You need the <span class="font-mono">settings.update</span> permission to change maintenance mode.</p>
    <?php endif; ?>
</div>
