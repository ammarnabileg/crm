<?php $this->extends('layouts.guest'); ?>
<?php $this->section('content'); ?>
<div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-xl font-bold text-white shadow-lg">H</span>
            <h1 class="mt-4 text-2xl font-bold text-slate-900">Choose a workspace</h1>
            <p class="mt-1 text-slate-600">Select which workspace to enter.</p>
        </div>
        <div class="card">
            <div class="card-body space-y-2">
                <?php foreach ($workspaces as $c): ?>
                    <form method="POST" action="<?= e(url('workspaces/switch')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="workspace_id" value="<?= e($c['id']) ?>">
                        <button class="flex w-full items-center gap-3 rounded-lg p-3 text-start ring-1 ring-slate-200 hover:bg-slate-50">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-100 font-semibold text-brand-700"><?= e(mb_substr($c['name'], 0, 1)) ?></span>
                            <span>
                                <span class="block font-medium text-slate-800"><?= e($c['name']) ?></span>
                                <span class="block text-xs text-slate-500"><?= e($c['status']) ?></span>
                            </span>
                        </button>
                    </form>
                <?php endforeach; ?>
                <a href="<?= e(url('workspaces/create')) ?>" class="mt-2 block rounded-lg border border-dashed border-slate-300 p-3 text-center text-sm font-medium text-brand-600 hover:bg-slate-50">+ Create a new workspace</a>
            </div>
        </div>
        <form method="POST" action="<?= e(url('logout')) ?>" class="mt-6 text-center">
            <?= csrf_field() ?>
            <button class="text-sm text-slate-500 hover:text-slate-700">Sign out</button>
        </form>
    </div>
</div>
<?php $this->endSection(); ?>
