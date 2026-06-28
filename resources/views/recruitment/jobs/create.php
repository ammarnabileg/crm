<?php /** @var string|null $error */ ?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">New job</h1>
    <p class="mt-1 text-sm text-slate-500">Create a draft, then publish to get a public application link.</p>
</div>

<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

<form method="post" action="/jobs" class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
    <?= csrf_field() ?>
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Title</label>
        <input name="title" required autofocus class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="Senior PHP Engineer">
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Location</label>
            <input name="location" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="Remote / Cairo">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Employment type</label>
            <input name="employment_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="Full-time">
        </div>
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
        <textarea name="description" rows="6" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="Role, responsibilities, requirements…"></textarea>
    </div>
    <div class="flex gap-2">
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create draft</button>
        <a href="/jobs" class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
    </div>
</form>
