<?php /** @var string|null $error */ ?>
<h1 class="mb-1 text-xl font-semibold text-slate-900">Create a workspace</h1>
<p class="mb-6 text-sm text-slate-500">An independent, isolated space for your hiring.</p>

<?php if ($error): ?>
    <div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="/workspaces" class="space-y-4">
    <?= csrf_field() ?>
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Workspace name</label>
        <input name="name" required autofocus class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="Acme Inc">
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Timezone</label>
            <input name="timezone" value="UTC" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Currency</label>
            <input name="currency" value="USD" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        </div>
    </div>
    <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Create workspace</button>
</form>
<p class="mt-6 text-center text-sm text-slate-500"><a href="/dashboard" class="font-medium text-indigo-600 hover:underline">Back</a></p>
