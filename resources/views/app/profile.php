<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<div class="mx-auto max-w-2xl">
    <h1 class="mb-6 text-2xl font-bold text-slate-900">Your profile</h1>

    <div class="card">
        <div class="card-body">
            <?php $this->include('partials.alerts'); ?>
            <form method="POST" action="<?= e(url('profile')) ?>" class="space-y-4">
                <?= csrf_field() ?>
                <?= method_field('PUT') ?>
                <div>
                    <label class="label" for="name">Full name</label>
                    <input class="input" id="name" name="name" value="<?= e(old('name', $user->name)) ?>" required>
                </div>
                <div>
                    <label class="label" for="email">Email</label>
                    <input class="input" id="email" name="email" type="email" value="<?= e(old('email', $user->email)) ?>" required>
                </div>
                <div>
                    <label class="label" for="locale">Language</label>
                    <select class="input" id="locale" name="locale">
                        <option value="en" <?= $user->locale === 'en' ? 'selected' : '' ?>>English</option>
                        <option value="ar" <?= $user->locale === 'ar' ? 'selected' : '' ?>>العربية</option>
                    </select>
                </div>

                <hr class="border-slate-200">
                <p class="text-sm font-medium text-slate-700">Change password <span class="font-normal text-slate-400">(leave blank to keep current)</span></p>
                <div>
                    <label class="label" for="password">New password</label>
                    <input class="input" id="password" name="password" type="password" autocomplete="new-password">
                </div>
                <div>
                    <label class="label" for="password_confirmation">Confirm new password</label>
                    <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
                </div>

                <button type="submit" class="btn-primary">Save changes</button>
            </form>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
