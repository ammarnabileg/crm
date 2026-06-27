<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= e($title) ?></h1>
        <p class="mt-1 text-sm text-slate-500">
            Create and restore backups straight from the dashboard — no terminal required.
            Backups are stored on this server under <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">storage/backups</code>.
        </p>
    </div>

    <?php $this->include('partials.alerts'); ?>

    <!-- Create backups -->
    <div class="card">
        <div class="card-body">
            <h2 class="font-semibold text-slate-900">Create a backup</h2>
            <p class="mt-1 text-sm text-slate-500">
                A database backup is a self-contained <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">.sql</code> file.
                A files backup is a <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">.zip</code> of your uploaded files.
            </p>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                <form method="POST" action="<?= e(url('system/backups/database')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-primary">Back up database</button>
                </form>
                <form method="POST" action="<?= e(url('system/backups/files')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-secondary">Back up files</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Existing backups -->
    <div class="card">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
            <h2 class="font-semibold text-slate-900">Existing backups</h2>
            <span class="badge-slate"><?= e((string) count($backups)) ?></span>
        </div>
        <div class="card-body">
            <?php if (! empty($listError)): ?>
                <div class="alert-error mb-4">
                    <svg class="h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    <span><?= e($listError) ?></span>
                </div>
            <?php endif; ?>

            <?php if (empty($backups)): ?>
                <div class="py-10 text-center text-slate-500">
                    <p class="text-sm">No backups yet. Create one above.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $backup): ?>
                                <tr>
                                    <td class="font-medium text-slate-800"><?= e($backup['name']) ?></td>
                                    <td>
                                        <?php if (($backup['type'] ?? '') === 'db'): ?>
                                            <span class="badge-brand">Database</span>
                                        <?php else: ?>
                                            <span class="badge-slate">Files</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-slate-500"><?= e($backup['size_human'] ?? '') ?></td>
                                    <td class="text-slate-500"><?= e($backup['created_human'] ?? '') ?></td>
                                    <td>
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="<?= e(url('system/backups/download?name=' . rawurlencode((string) $backup['name']))) ?>"
                                               class="btn-ghost px-3 py-1.5 text-sm">Download</a>

                                            <?php if (($backup['type'] ?? '') === 'db'): ?>
                                                <form method="POST" action="<?= e(url('system/backups/restore')) ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="name" value="<?= e($backup['name']) ?>">
                                                    <input type="hidden" name="confirm" value="1">
                                                    <button type="submit" class="btn-secondary px-3 py-1.5 text-sm" data-confirm="Restore the database from <?= e($backup['name']) ?>? This OVERWRITES all current data and cannot be undone. Make sure you have a fresh backup first.">Restore</button>
                                                </form>
                                            <?php endif; ?>

                                            <form method="POST" action="<?= e(url('system/backups/delete')) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_method" value="DELETE">
                                                <input type="hidden" name="name" value="<?= e($backup['name']) ?>">
                                                <button type="submit" class="btn-danger px-3 py-1.5 text-sm" data-confirm="Delete <?= e($backup['name']) ?>? This cannot be undone.">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Restore warning -->
    <div class="alert-error">
        <svg class="h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        <div>
            <p class="font-medium">Restoring a database is destructive.</p>
            <p class="mt-1 text-sm">
                A restore drops and recreates every table from the chosen <code class="rounded bg-red-100 px-1 py-0.5 text-xs">.sql</code> file,
                permanently overwriting all current data. Always create a fresh database backup immediately before you restore.
            </p>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
