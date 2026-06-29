<?php
/** @var list<array{name:string,ok:bool,detail:string}> $requirements */
/** @var bool $satisfied */
/** @var array<string,string> $commands */
/** @var string|null $consoleOutput */
/** @var array{host:string,port:string,database:string,username:string} $db */
/** @var string|null $error */
$field = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
?>
<h1 class="mb-1 text-xl font-semibold text-slate-900">Install HaHireAI</h1>
<p class="mb-6 text-sm text-slate-500">Enter your database and owner account — the page does the rest. No terminal, no Composer, no manual SQL.</p>

<?php if ($error): ?>
    <div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Server requirements</h2>
    <ul class="space-y-1 text-sm">
        <?php foreach ($requirements as $req): ?>
            <li class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-1.5">
                <span class="text-slate-600"><?= e($req['name']) ?></span>
                <span class="<?= $req['ok'] ? 'text-emerald-600' : 'text-rose-600' ?> font-medium"><?= $req['ok'] ? '✓' : '✗' ?> <?= e($req['detail']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<form method="post" action="/install" class="space-y-4">
    <?= csrf_field() ?>

    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Database</h2>
    <div class="grid grid-cols-3 gap-3">
        <div class="col-span-2"><label class="mb-1 block text-sm font-medium text-slate-700">Host</label><input name="db_host" value="<?= e($db['host']) ?>" required class="<?= $field ?>" placeholder="127.0.0.1"></div>
        <div><label class="mb-1 block text-sm font-medium text-slate-700">Port</label><input name="db_port" value="<?= e($db['port']) ?>" required class="<?= $field ?>" placeholder="3306"></div>
    </div>
    <div><label class="mb-1 block text-sm font-medium text-slate-700">Database name</label><input name="db_database" value="<?= e($db['database']) ?>" required class="<?= $field ?>" placeholder="hahireai"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="mb-1 block text-sm font-medium text-slate-700">Username</label><input name="db_username" value="<?= e($db['username']) ?>" required class="<?= $field ?>" placeholder="db user"></div>
        <div><label class="mb-1 block text-sm font-medium text-slate-700">Password</label><input name="db_password" type="password" class="<?= $field ?>" placeholder="db password"></div>
    </div>

    <h2 class="pt-2 text-xs font-semibold uppercase tracking-wide text-slate-400">First System Owner</h2>
    <div><label class="mb-1 block text-sm font-medium text-slate-700">Full name</label><input name="name" required class="<?= $field ?>" placeholder="Jane Doe"></div>
    <div><label class="mb-1 block text-sm font-medium text-slate-700">Email</label><input name="email" type="email" required class="<?= $field ?>" placeholder="owner@company.com"></div>
    <div><label class="mb-1 block text-sm font-medium text-slate-700">Password</label><input name="password" type="password" required minlength="8" class="<?= $field ?>" placeholder="At least 8 characters"></div>

    <button type="submit" <?= $satisfied ? '' : 'disabled' ?> class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
        Run installation
    </button>
</form>

<div class="mt-8 border-t border-slate-200 pt-6">
    <h2 class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Setup console</h2>
    <p class="mb-3 text-xs text-slate-400">Run a setup task and see its output. Available only during setup; locks after install. (Safe, fixed commands — not a shell.)</p>
    <div class="flex flex-wrap gap-2">
        <?php foreach ($commands as $key => $label): ?>
            <form method="post" action="/install/console">
                <?= csrf_field() ?><input type="hidden" name="command" value="<?= e($key) ?>">
                <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50" title="<?= e($label) ?>"><?= e($key) ?></button>
            </form>
        <?php endforeach; ?>
    </div>
    <pre class="mt-3 max-h-64 overflow-auto rounded-lg bg-slate-900 px-4 py-3 font-mono text-xs leading-relaxed text-emerald-300"><?= $consoleOutput !== null ? e($consoleOutput) : 'Output appears here.' ?></pre>
</div>
