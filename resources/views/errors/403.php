<?php $this->extends('layouts.guest'); ?>
<?php $this->section('content'); ?>
<div class="flex min-h-screen items-center justify-center px-4">
    <div class="text-center">
        <p class="text-7xl font-extrabold text-amber-500">403</p>
        <h1 class="mt-4 text-2xl font-bold text-slate-900">Access denied</h1>
        <p class="mt-2 text-slate-600"><?= e($message ?: "You don't have permission to view this page.") ?></p>
        <a href="<?= e(url('dashboard')) ?>" class="btn-primary mt-6">Back to dashboard</a>
    </div>
</div>
<?php $this->endSection(); ?>
