<?php
/** @var list<array<string,mixed>> $profiles */
/** @var bool $entitled */
/** @var string|null $status */
/** @var string|null $error */
$providers = ['openai', 'anthropic', 'gemini', 'google', 'azure', 'deepgram', 'elevenlabs', 'heygen', 'openrouter'];
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">AI Provider Profiles</h1>
    <p class="mt-1 text-sm text-slate-500">Reusable provider / model / key profiles. Set one as default and pick it in interviews and workflows. Your keys are encrypted and never shown.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>
<?php if (! $entitled): ?>
    <div class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800">AI is a premium service. <a href="/billing" class="font-semibold underline">Enable it in Build Your Workspace</a>.</div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Your profiles</h2>
        <?php if ($profiles === []): ?>
            <p class="px-5 py-6 text-sm text-slate-400">No profiles yet. Create one on the right.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($profiles as $p): ?>
                    <li class="flex items-center justify-between px-5 py-3 text-sm">
                        <div>
                            <span class="font-medium text-slate-800"><?= e($p['name']) ?></span>
                            <?php if ((int) $p['is_default'] === 1): ?><span class="ml-2 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Default</span><?php endif; ?>
                            <div class="text-xs text-slate-400"><?= e($p['provider']) ?><?= $p['model'] ? ' · ' . e($p['model']) : '' ?> · key <?= e($p['key_hint'] ?? '••••') ?></div>
                        </div>
                        <div class="flex items-center gap-3">
                            <?php if ((int) $p['is_default'] !== 1): ?>
                                <form method="post" action="/ai/profiles/<?= e($p['id']) ?>/default"><?= csrf_field() ?><button class="text-xs font-medium text-indigo-600 hover:underline">Make default</button></form>
                            <?php endif; ?>
                            <form method="post" action="/ai/profiles/<?= e($p['id']) ?>/delete" onsubmit="return confirm('Delete this profile?')"><?= csrf_field() ?><button class="text-xs font-medium text-rose-600 hover:underline">Delete</button></form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <?php if ($entitled): ?>
    <form method="post" action="/ai/profiles" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
        <?= csrf_field() ?>
        <div class="text-sm font-semibold text-slate-900">New profile</div>
        <input name="name" placeholder="e.g. OpenAI Production" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="provider" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <?php foreach ($providers as $pr): ?><option value="<?= e($pr) ?>"><?= e(ucfirst($pr)) ?></option><?php endforeach; ?>
        </select>
        <input name="model" placeholder="Model (optional, e.g. gpt-4o)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input name="api_key" type="password" placeholder="API key (encrypted at rest)" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <label class="flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" name="make_default" value="1"> Make default</label>
        <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create profile</button>
    </form>
    <?php endif; ?>
</div>
