<?php
/** @var array{provider:string,model:?string,fallback_provider:?string,use_platform_key:bool} $config */
/** @var list<array{provider:string,key_hint:?string}> $keyHints */
/** @var list<string> $providers */
/** @var array{sessions:int,tokens:int,cost_cents:int} $usage */
/** @var bool $canConfigure */
/** @var bool $canManageKeys */
/** @var string|null $status */
/** @var string|null $error */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">AI</h1>
    <p class="mt-1 text-sm text-slate-500">The central engine every recruitment workflow can route through. Keys are encrypted and never shown.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">AI runs</div><div class="mt-1 text-2xl font-bold text-slate-900"><?= e($usage['sessions']) ?></div></div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Tokens</div><div class="mt-1 text-2xl font-bold text-slate-900"><?= e($usage['tokens']) ?></div></div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Cost</div><div class="mt-1 text-2xl font-bold text-slate-900">$<?= number_format($usage['cost_cents'] / 100, 2) ?></div></div>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-slate-900">Provider</h2>
        <form method="post" action="/ai/provider" class="space-y-3">
            <?= csrf_field() ?>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Provider</label>
                <select name="provider" <?= $canConfigure ? '' : 'disabled' ?> class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <?php foreach ($providers as $p): ?>
                        <option value="<?= e($p) ?>" <?= $config['provider'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Model (optional)</label>
                <input name="model" value="<?= e($config['model'] ?? '') ?>" <?= $canConfigure ? '' : 'disabled' ?> class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. gpt-4o, claude-3-5">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Fallback provider (optional)</label>
                <input name="fallback_provider" value="<?= e($config['fallback_provider'] ?? '') ?>" <?= $canConfigure ? '' : 'disabled' ?> class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. echo">
            </div>
            <?php if ($canConfigure): ?><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save provider</button><?php endif; ?>
        </form>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-slate-900">API keys (encrypted)</h2>
        <?php if ($keyHints === []): ?>
            <p class="mb-3 text-sm text-slate-400">No keys stored. The built-in <span class="font-mono">echo</span> provider works without a key.</p>
        <?php else: ?>
            <ul class="mb-3 space-y-1 text-sm">
                <?php foreach ($keyHints as $k): ?>
                    <li class="flex justify-between rounded-md bg-slate-50 px-3 py-1.5"><span class="font-medium text-slate-700"><?= e($k['provider']) ?></span><span class="font-mono text-slate-400"><?= e($k['key_hint']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($canManageKeys): ?>
            <form method="post" action="/ai/keys" class="space-y-2">
                <?= csrf_field() ?>
                <input name="provider" placeholder="provider (e.g. openai)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="api_key" type="password" placeholder="API key (stored encrypted)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Save key</button>
            </form>
        <?php endif; ?>
    </div>
</div>
