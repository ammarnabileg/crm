<?php $this->extends('layouts.guest'); ?>
<?php $this->section('content'); ?>
<div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <a href="<?= e(url('/')) ?>" class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-xl font-bold text-white shadow-lg">H</a>
            <h1 class="mt-4 text-2xl font-bold text-slate-900">Choose a new password</h1>
        </div>
        <div class="card">
            <div class="card-body">
                <?php $this->include('partials.alerts'); ?>
                <form method="POST" action="<?= e(url('reset-password')) ?>" class="space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
                    <div>
                        <label class="label" for="email">Email</label>
                        <input class="input" id="email" name="email" type="email" value="<?= e($email ?? old('email')) ?>" required>
                    </div>
                    <div>
                        <label class="label" for="password">New password</label>
                        <input class="input" id="password" name="password" type="password" autocomplete="new-password" required>
                    </div>
                    <div>
                        <label class="label" for="password_confirmation">Confirm new password</label>
                        <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn-primary w-full">Reset password</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
