<?php $this->extends('layouts.guest'); ?>
<?php $this->section('content'); ?>
<div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-xl font-bold text-white shadow-lg">H</span>
            <h1 class="mt-4 text-2xl font-bold text-slate-900">Create your company</h1>
            <p class="mt-1 text-slate-600">You'll be the owner with full control.</p>
        </div>
        <div class="card">
            <div class="card-body">
                <?php $this->include('partials.alerts'); ?>
                <form method="POST" action="<?= e(url('companies')) ?>" class="space-y-4">
                    <?= csrf_field() ?>
                    <div>
                        <label class="label" for="name">Company name</label>
                        <input class="input" id="name" name="name" value="<?= e(old('name')) ?>" autofocus required>
                    </div>
                    <button type="submit" class="btn-primary w-full">Create company</button>
                </form>
            </div>
        </div>
        <?php if (! empty(auth()->user()->companies())): ?>
            <p class="mt-6 text-center text-sm text-slate-600">
                <a href="<?= e(url('companies/select')) ?>" class="font-semibold text-brand-600 hover:text-brand-700">Or switch to an existing company</a>
            </p>
        <?php endif; ?>
    </div>
</div>
<?php $this->endSection(); ?>
