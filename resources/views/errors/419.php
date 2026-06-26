<?php $this->extends('layouts.guest'); ?>
<?php $this->section('content'); ?>
<div class="flex min-h-screen items-center justify-center px-4">
    <div class="text-center">
        <p class="text-7xl font-extrabold text-amber-500">419</p>
        <h1 class="mt-4 text-2xl font-bold text-slate-900">Page expired</h1>
        <p class="mt-2 text-slate-600">Your session timed out for security. Please go back and try again.</p>
        <a href="<?= e(url('login')) ?>" class="btn-primary mt-6">Back to login</a>
    </div>
</div>
<?php $this->endSection(); ?>
