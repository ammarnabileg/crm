<?php $this->extends('layouts.guest'); ?>
<?php $this->section('content'); ?>
<div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <a href="<?= e(url('/')) ?>" class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-xl font-bold text-white shadow-lg">H</a>
            <h1 class="mt-4 text-2xl font-bold text-slate-900">Create your account</h1>
            <p class="mt-1 text-slate-600">Start your <?= e(config('app.name')) ?> workspace</p>
        </div>

        <div class="card">
            <div class="card-body">
                <?php $this->include('partials.alerts'); ?>
                <form method="POST" action="<?= e(url('register')) ?>" class="space-y-4">
                    <?= csrf_field() ?>
                    <div>
                        <label class="label" for="name">Full name</label>
                        <input class="input" id="name" name="name" value="<?= e(old('name')) ?>" autofocus required>
                    </div>
                    <div>
                        <label class="label" for="email">Email</label>
                        <input class="input" id="email" name="email" type="email" value="<?= e(old('email')) ?>" required>
                    </div>
                    <div>
                        <label class="label" for="company_name">Company name <span class="text-slate-400">(optional)</span></label>
                        <input class="input" id="company_name" name="company_name" value="<?= e(old('company_name')) ?>" placeholder="You can create this later">
                    </div>
                    <div>
                        <label class="label" for="password">Password</label>
                        <input class="input" id="password" name="password" type="password" autocomplete="new-password" required>
                    </div>
                    <div>
                        <label class="label" for="password_confirmation">Confirm password</label>
                        <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn-primary w-full">Create account</button>
                </form>
            </div>
        </div>

        <p class="mt-6 text-center text-sm text-slate-600">
            Already have an account?
            <a href="<?= e(url('login')) ?>" class="font-semibold text-brand-600 hover:text-brand-700">Sign in</a>
        </p>
    </div>
</div>
<?php $this->endSection(); ?>
