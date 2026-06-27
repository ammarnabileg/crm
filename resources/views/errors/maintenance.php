<?php $this->extends('layouts.guest'); ?>
<?php $this->section('content'); ?>
<div class="flex min-h-screen items-center justify-center px-4">
    <div class="max-w-lg text-center">
        <p class="text-7xl font-extrabold text-amber-500">503</p>
        <h1 class="mt-4 text-2xl font-bold text-slate-900">We'll be right back</h1>
        <p class="mt-2 text-slate-600">
            <?= e(($message ?? '') !== '' ? $message : 'The site is temporarily down for scheduled maintenance. Please check back soon.') ?>
        </p>
    </div>
</div>
<?php $this->endSection(); ?>
