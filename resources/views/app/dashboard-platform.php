<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Platform overview</h1>
    <p class="mt-1 text-slate-600">You're signed in as a platform super admin.</p>
</div>

<div class="grid gap-4 sm:grid-cols-3">
    <div class="card"><div class="card-body">
        <p class="text-sm font-medium text-slate-500">Companies</p>
        <p class="mt-1 text-3xl font-bold text-slate-900"><?= e($companyCount) ?></p>
    </div></div>
    <div class="card"><div class="card-body">
        <p class="text-sm font-medium text-slate-500">Users</p>
        <p class="mt-1 text-3xl font-bold text-slate-900"><?= e($userCount) ?></p>
    </div></div>
    <div class="card"><div class="card-body">
        <p class="text-sm font-medium text-slate-500">Active plans</p>
        <p class="mt-1 text-3xl font-bold text-slate-900"><?= e($planCount) ?></p>
    </div></div>
</div>

<div class="mt-6 card">
    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
        <h2 class="font-semibold text-slate-900">Newest companies</h2>
        <a href="<?= e(url('admin/companies')) ?>" class="text-sm font-medium text-brand-600 hover:text-brand-700">View all</a>
    </div>
    <div class="card-body">
        <?php if (empty($recent)): ?>
            <div class="py-10 text-center text-slate-500">
                <p class="text-sm">No companies yet.</p>
                <a href="<?= e(url('companies/create')) ?>" class="btn-primary mt-4">Create the first company</a>
            </div>
        <?php else: ?>
            <table class="table-base">
                <thead><tr><th>Company</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                    <?php foreach ($recent as $c): ?>
                        <tr>
                            <td class="font-medium text-slate-800"><?= e($c['name']) ?></td>
                            <td><span class="badge-slate"><?= e($c['status']) ?></span></td>
                            <td class="text-slate-500"><?= e($c['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php $this->endSection(); ?>
