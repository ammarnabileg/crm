<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Welcome back, <?= e(explode(' ', auth()->user()->name)[0]) ?> 👋</h1>
    <p class="mt-1 text-slate-600">Here's what's happening at <?= e($company->name ?? config('app.name')) ?>.</p>
</div>

<div class="grid gap-4 sm:grid-cols-3">
    <div class="card"><div class="card-body">
        <p class="text-sm font-medium text-slate-500">Active members</p>
        <p class="mt-1 text-3xl font-bold text-slate-900"><?= e($stats['members']) ?></p>
    </div></div>
    <div class="card"><div class="card-body">
        <p class="text-sm font-medium text-slate-500">Roles</p>
        <p class="mt-1 text-3xl font-bold text-slate-900"><?= e($stats['roles']) ?></p>
    </div></div>
    <div class="card"><div class="card-body">
        <p class="text-sm font-medium text-slate-500">Current plan</p>
        <p class="mt-1 text-2xl font-bold text-slate-900"><?= e($stats['plan']) ?></p>
        <?php if ($subscription && $subscription->onTrial()): ?>
            <span class="badge-amber mt-2">Trial</span>
        <?php elseif ($subscription): ?>
            <span class="badge-green mt-2">Active</span>
        <?php endif; ?>
    </div></div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="font-semibold text-slate-900">Recent activity</h2>
            </div>
            <div class="card-body">
                <?php if (empty($recent)): ?>
                    <div class="py-10 text-center text-slate-500">
                        <p class="text-sm">No activity yet. As your team works, you'll see it here.</p>
                    </div>
                <?php else: ?>
                    <ul class="space-y-4">
                        <?php foreach ($recent as $item): ?>
                            <li class="flex items-start gap-3">
                                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-brand-500"></span>
                                <div>
                                    <p class="text-sm text-slate-800"><?= e($item['description'] ?: $item['action']) ?></p>
                                    <p class="text-xs text-slate-400"><?= e($item['created_at']) ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="font-semibold text-slate-900">Quick actions</h2>
            </div>
            <div class="card-body space-y-2">
                <a href="<?= e(url('profile')) ?>" class="btn-secondary w-full justify-start">Edit your profile</a>
                <a href="<?= e(url('companies/create')) ?>" class="btn-secondary w-full justify-start">Create another company</a>
            </div>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
