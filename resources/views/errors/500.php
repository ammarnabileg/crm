<?php $this->extends('layouts.guest'); ?>
<?php $this->section('content'); ?>
<div class="flex min-h-screen items-center justify-center px-4">
    <div class="text-center">
        <p class="text-7xl font-extrabold text-red-500">500</p>
        <h1 class="mt-4 text-2xl font-bold text-slate-900">Server error</h1>
        <p class="mt-2 text-slate-600">Something went wrong on our end. The issue has been logged.</p>
        <a href="<?= e(url('/')) ?>" class="btn-primary mt-6">Go back home</a>
    </div>
</div>
<?php $this->endSection(); ?>
