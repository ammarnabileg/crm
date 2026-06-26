<?php $this->extends('layouts.guest'); ?>
<?php $this->section('content'); ?>
<div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <a href="<?= e(url('/')) ?>" class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-xl font-bold text-white shadow-lg">H</a>
            <h1 class="mt-4 text-2xl font-bold text-slate-900">Welcome back</h1>
            <p class="mt-1 text-slate-600">Sign in to <?= e(config('app.name')) ?></p>
        </div>

        <div class="card">
            <div class="card-body">
                <?php $this->include('partials.alerts'); ?>
                <form method="POST" action="<?= e(url('login')) ?>" class="space-y-4">
                    <?= csrf_field() ?>
                    <div>
                        <label class="label" for="email">Email</label>
                        <input class="input" id="email" name="email" type="email" value="<?= e(old('email')) ?>" autofocus required>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="label" for="password">Password</label>
                            <a href="<?= e(url('forgot-password')) ?>" class="text-sm font-medium text-brand-600 hover:text-brand-700">Forgot?</a>
                        </div>
                        <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        Remember me
                    </label>
                    <button type="submit" class="btn-primary w-full">Sign in</button>
                </form>
            </div>
        </div>

        <p class="mt-6 text-center text-sm text-slate-600">
            Don't have an account?
            <a href="<?= e(url('register')) ?>" class="font-semibold text-brand-600 hover:text-brand-700">Create one</a>
        </p>
    </div>
</div>
<?php $this->endSection(); ?>
