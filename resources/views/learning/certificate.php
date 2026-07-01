<?php
/** @var array<string,mixed> $cert */
?>
<div class="mx-auto max-w-3xl px-6 py-10">
    <div class="mb-4 flex justify-end print:hidden">
        <button onclick="window.print()" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Print / Save PDF</button>
    </div>
    <div class="rounded-2xl border-4 border-double border-indigo-200 bg-white p-12 text-center shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Certificate of Completion</p>
        <div class="mx-auto my-6 h-px w-24 bg-indigo-200"></div>
        <p class="text-sm text-slate-500">This certifies that</p>
        <h1 class="mt-2 text-3xl font-bold text-slate-900"><?= e($cert['user_name'] ?? '—') ?></h1>
        <p class="mt-4 text-sm text-slate-500">has successfully completed</p>
        <h2 class="mt-2 text-xl font-semibold text-indigo-700"><?= e($cert['program_title'] ?? $cert['title']) ?></h2>
        <p class="mt-6 text-sm text-slate-500">with a score of <span class="font-semibold text-slate-700"><?= (int) $cert['percent'] ?>%</span></p>
        <div class="mt-8 flex items-center justify-between text-xs text-slate-400">
            <span>Issued <?= e(substr((string) ($cert['issued_at'] ?? ''), 0, 10)) ?></span>
            <span>Serial: <span class="font-mono text-slate-600"><?= e($cert['serial']) ?></span></span>
        </div>
    </div>
    <p class="mt-3 text-center text-[11px] text-slate-400">Verify this certificate by its serial within the issuing workspace.</p>
</div>
