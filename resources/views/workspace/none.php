<?php /** @var array<string,mixed> $user */ ?>
<div class="mx-auto max-w-xl py-10 text-center">
    <h1 class="text-2xl font-semibold text-slate-900">Welcome, <?= e($user['name'] ?? '') ?> 👋</h1>
    <p class="mt-2 text-slate-500">You don't belong to any workspace yet. Create one or join with an invitation.</p>
    <div class="mt-8 grid gap-4 sm:grid-cols-2">
        <a href="/workspaces/create" class="rounded-2xl border border-slate-200 bg-white p-6 text-left shadow-sm hover:border-indigo-300">
            <div class="text-lg font-semibold text-slate-900">Create a workspace</div>
            <p class="mt-1 text-sm text-slate-500">Start hiring with your own isolated workspace.</p>
        </a>
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-left">
            <div class="text-lg font-semibold text-slate-900">Join a workspace</div>
            <p class="mt-1 text-sm text-slate-500">Have an invitation code? Ask your admin to invite you by email.</p>
        </div>
    </div>
</div>
