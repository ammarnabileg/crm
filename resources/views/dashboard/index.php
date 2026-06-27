<?php
/** @var array<string,mixed> $user */
/** @var array<string,mixed> $workspace */
/** @var list<array<string,mixed>> $workspaces */
/** @var int $permissionCount */
/** @var bool $isSystemOwner */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Dashboard</h1>
    <p class="mt-1 text-sm text-slate-500">Workspace command center — <?= e($workspace['name']) ?></p>
</div>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">Your access</div>
        <div class="mt-2 text-3xl font-bold text-slate-900"><?= e($permissionCount) ?></div>
        <div class="text-sm text-slate-500">permissions in this workspace</div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">Workspaces</div>
        <div class="mt-2 text-3xl font-bold text-slate-900"><?= e(count($workspaces)) ?></div>
        <div class="text-sm text-slate-500">you belong to</div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">Account</div>
        <div class="mt-2 text-lg font-semibold text-slate-900"><?= $isSystemOwner ? 'System Owner' : 'User' ?></div>
        <div class="text-sm text-slate-500"><?= e($user['email']) ?></div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">Recruitment</div>
        <div class="mt-2 text-lg font-semibold text-slate-400">Coming in Phase 10</div>
        <div class="text-sm text-slate-500">Jobs, pipeline, AI interviews</div>
    </div>
</div>

<div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-sm font-semibold text-slate-900">Your sidebar is generated from your permissions</h2>
    <p class="mt-1 text-sm text-slate-500">
        The single sidebar on the left is built dynamically from your current workspace, permissions,
        subscription, and enabled modules — never from a fixed role. Build custom roles to change what a
        member sees and can do.
    </p>
</div>
