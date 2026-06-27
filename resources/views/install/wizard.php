<?php
/** @var list<array{name:string,ok:bool,detail:string}> $requirements */
/** @var bool $satisfied */
/** @var string|null $error */
?>
<h1 class="mb-1 text-xl font-semibold text-slate-900">Install HaHireAI</h1>
<p class="mb-6 text-sm text-slate-500">Set up the platform and create the first System Owner. No terminal required.</p>

<?php if ($error): ?>
    <div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Server requirements</h2>
    <ul class="space-y-1 text-sm">
        <?php foreach ($requirements as $req): ?>
            <li class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-1.5">
                <span class="text-slate-600"><?= e($req['name']) ?></span>
                <span class="<?= $req['ok'] ? 'text-emerald-600' : 'text-rose-600' ?> font-medium">
                    <?= $req['ok'] ? '✓' : '✗' ?> <?= e($req['detail']) ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<form method="post" action="/install" class="space-y-4">
    <?= csrf_field() ?>
    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400">First System Owner</h2>
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Full name</label>
        <input name="name" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="Jane Doe">
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
        <input name="email" type="email" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="owner@company.com">
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Password</label>
        <input name="password" type="password" required minlength="8" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="At least 8 characters">
    </div>
    <button type="submit" <?= $satisfied ? '' : 'disabled' ?> class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
        Run installation
    </button>
</form>
