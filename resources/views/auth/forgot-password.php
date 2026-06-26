<?php $this->extends('layouts.guest'); ?>
<?php $this->section('content'); ?>
<div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <a href="<?= e(url('/')) ?>" class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-xl font-bold text-white shadow-lg">H</a>
            <h1 class="mt-4 text-2xl font-bold text-slate-900">Reset your password</h1>
            <p class="mt-1 text-slate-600">We'll email you a secure reset link.</p>
        </div>
        <div class="card">
            <div class="card-body">
                <?php $this->include('partials.alerts'); ?>
                <form method="POST" action="<?= e(url('forgot-password')) ?>" class="space-y-4">
                    <?= csrf_field() ?>
                    <div>
                        <label class="label" for="email">Email</label>
                        <input class="input" id="email" name="email" type="email" value="<?= e(old('email')) ?>" autofocus required>
                    </div>
                    <button type="submit" class="btn-primary w-full">Email password reset link</button>
                </form>
            </div>
        </div>
        <p class="mt-6 text-center text-sm text-slate-600">
            <a href="<?= e(url('login')) ?>" class="font-semibold text-brand-600 hover:text-brand-700">Back to sign in</a>
        </p>
    </div>
</div>
<?php $this->endSection(); ?>
