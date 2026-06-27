<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * @var \App\Models\Application $application
 * @var array<string,mixed>|null $candidate
 * @var string $jobTitle @var ?string $status
 * @var array<int,array<string,mixed>> $timeline
 */
?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900"><?= e((string) ($candidate['name'] ?? 'Candidate')) ?></h1>
            <p class="text-sm text-slate-500"><?= e($jobTitle) ?> · <?= e((string) ($candidate['email'] ?? '')) ?></p>
        </div>
        <span class="badge-green"><?= e((string) ($status ?? 'applied')) ?></span>
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Timeline</h2>
        <?php if ($timeline === []): ?>
            <p class="text-sm text-slate-500">No activity yet.</p>
        <?php else: ?>
            <ol class="relative space-y-4 border-s border-slate-200 ps-5">
                <?php foreach ($timeline as $entry): ?>
                    <li class="relative">
                        <span class="absolute -start-[1.45rem] mt-1 h-2.5 w-2.5 rounded-full bg-indigo-500"></span>
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-slate-800"><?= e((string) ($entry['title'] ?? $entry['type'] ?? 'Event')) ?></span>
                            <span class="text-xs text-slate-400"><?= e((string) ($entry['at'] ?? '')) ?></span>
                        </div>
                        <span class="text-xs uppercase tracking-wide text-slate-400"><?= e((string) ($entry['type'] ?? '')) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</div>
<?php $this->endSection(); ?>
