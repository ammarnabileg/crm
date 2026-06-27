<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * @var string                                                                     $title
 * @var array<int, array{name:string, size:int, modified:int}>                     $files
 * @var ?string                                                                     $selected
 * @var array<int, array{level:string, time:?string, message:string, fix:?string}> $lines
 */
$human = static function (int $bytes): string {
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    $units = ['KB', 'MB', 'GB'];
    $value = $bytes / 1024;
    $i = 0;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }
    return number_format($value, $value >= 10 ? 0 : 1) . ' ' . $units[$i];
};
// Tailwind text colours per level for the dark console.
$levelColor = [
    'error'   => 'text-red-400',
    'warning' => 'text-amber-300',
    'info'    => 'text-sky-300',
    'debug'   => 'text-slate-500',
];
$levelBadge = [
    'error'   => 'badge-red',
    'warning' => 'badge-amber',
    'info'    => 'badge-slate',
    'debug'   => 'badge-slate',
];
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900"><?= e($title) ?></h1>
            <p class="text-sm text-slate-500">Read recent log output and get a suggested fix for common errors — no terminal needed.</p>
        </div>
    </div>

    <?php $this->include('partials.alerts'); ?>

    <?php if (empty($files)): ?>
        <div class="card">
            <div class="card-body">
                <div class="flex flex-col items-center justify-center gap-2 py-12 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </span>
                    <p class="font-medium text-slate-700">No log files yet</p>
                    <p class="max-w-sm text-sm text-slate-400">When the app records errors or events they appear here. Nothing has been logged so far — that's a good sign.</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[18rem,1fr]">

            <!-- Left: file list -->
            <div class="card self-start">
                <div class="card-body">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Log files</p>
                    <ul class="space-y-1">
                        <?php foreach ($files as $file): ?>
                            <?php $active = $file['name'] === $selected; ?>
                            <li>
                                <a href="<?= e(url('system/logs?file=' . rawurlencode($file['name']))) ?>"
                                   class="block rounded-lg px-3 py-2 text-sm <?= $active ? 'bg-brand-50 ring-1 ring-inset ring-brand-200' : 'hover:bg-slate-50' ?>">
                                    <span class="block truncate font-medium <?= $active ? 'text-brand-700' : 'text-slate-700' ?>"><?= e($file['name']) ?></span>
                                    <span class="mt-0.5 flex items-center justify-between text-xs text-slate-400">
                                        <span><?= e($human($file['size'])) ?></span>
                                        <span><?= e($file['modified'] > 0 ? date('Y-m-d H:i', $file['modified']) : '—') ?></span>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Right: console -->
            <div class="card min-w-0">
                <div class="card-body space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-mono text-sm font-semibold text-slate-800"><?= e($selected ?? '—') ?></p>
                            <p class="text-xs text-slate-400">Showing the last <?= count($lines) ?> line<?= count($lines) === 1 ? '' : 's' ?> (newest at the bottom).</p>
                        </div>
                        <?php if ($selected !== null): ?>
                            <form method="POST" action="<?= e(url('system/logs/clear')) ?>"
                                  onsubmit="return confirm('Clear all contents of <?= e($selected) ?>? This cannot be undone.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="file" value="<?= e($selected) ?>">
                                <button type="submit" class="btn-danger">Clear log</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($lines)): ?>
                        <div class="rounded-lg border border-dashed border-slate-200 py-10 text-center text-sm text-slate-400">
                            This log is empty.
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto rounded-lg bg-slate-900 p-4 font-mono text-xs leading-relaxed text-slate-200">
                            <pre class="whitespace-pre-wrap break-words"><?php foreach ($lines as $line): ?><?php
                                $color = $levelColor[$line['level']] ?? 'text-slate-200';
                                ?><div class="py-0.5"><span class="<?= $color ?>"><?php
                                    if ($line['time'] !== null && $line['time'] !== '') {
                                        echo '[' . e($line['time']) . '] ';
                                    }
                                    echo '<span class="font-semibold uppercase">' . e($line['level']) . '</span> ';
                                    echo e($line['message']);
                                ?></span><?php if (! empty($line['fix'])): ?><div class="mt-0.5 flex items-start gap-1 rounded bg-amber-500/10 px-2 py-1 text-amber-300"><svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg><span><span class="font-semibold">Suggested fix:</span> <?= e($line['fix']) ?></span></div><?php endif; ?></div><?php endforeach; ?></pre>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-400">
                            <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-red-400"></span>error</span>
                            <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-300"></span>warning</span>
                            <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-sky-300"></span>info</span>
                            <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-slate-500"></span>debug</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
