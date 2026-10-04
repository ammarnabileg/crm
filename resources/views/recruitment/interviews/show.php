<?php
/** @var array<string,mixed> $interview */
/** @var list<array<string,mixed>> $messages */
/** @var array<string,mixed>|null $assessment */
$iv = $interview;
$score = $iv['score'] !== null ? (int) $iv['score'] : null;
$sc = $score === null ? 'text-slate-400' : ($score >= 75 ? 'text-emerald-700' : ($score >= 55 ? 'text-amber-700' : 'text-rose-700'));
?>
<div class="mb-6 flex items-start justify-between">
    <div>
        <a href="/interviews" class="text-xs text-slate-400 hover:text-slate-600">← AI Interviews</a>
        <h1 class="mt-1 text-2xl font-semibold text-slate-900"><?= e($iv['candidate_name']) ?></h1>
        <p class="mt-1 text-sm text-slate-500">
            <?= e($iv['job_title']) ?> ·
            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium uppercase text-slate-500"><?= e($iv['mode'] ?? 'text') ?></span>
            · <?= e($iv['status']) ?>
            <?php if (! empty($iv['ai_provider'])): ?> · provider <?= e($iv['ai_provider']) ?><?php endif; ?>
            <?php if (! empty($iv['completed_at'])): ?> · completed <?= e($iv['completed_at']) ?> UTC<?php endif; ?>
        </p>
    </div>
    <div class="text-right">
        <div class="text-2xl font-bold <?= $sc ?>"><?= $score === null ? '—' : e($score) . '/100' ?></div>
        <div class="text-xs text-slate-400"><?= e($iv['recommendation'] ?? 'pending') ?></div>
        <a href="/candidates/<?= e($iv['candidate_user_id']) ?>" class="mt-2 inline-block rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Candidate file</a>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-slate-900">Transcript</h2>
        <?php if ($messages !== []): ?>
            <div class="space-y-3">
                <?php foreach ($messages as $m): ?>
                    <?php $isCand = (string) $m['role'] === 'candidate'; ?>
                    <div class="flex <?= $isCand ? 'justify-end' : 'justify-start' ?>">
                        <div class="max-w-[80%] rounded-2xl px-4 py-2 text-sm <?= $isCand ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-800' ?>"><?= nl2br(e((string) $m['content'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif (! empty($iv['transcript'])): ?>
            <pre class="whitespace-pre-wrap font-sans text-sm text-slate-600"><?= e((string) $iv['transcript']) ?></pre>
        <?php else: ?>
            <p class="text-sm text-slate-400">No transcript recorded for this interview.</p>
        <?php endif; ?>
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-2 text-sm font-semibold text-slate-900">Summary</h2>
            <p class="text-sm text-slate-600"><?= e((string) ($iv['summary'] ?? 'No summary.')) ?></p>
        </div>
        <?php if ($assessment !== null && ! empty($assessment['summary'])): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-slate-900">AI assessment <span class="font-normal text-slate-400">(advisory)</span></h2>
                <p class="text-sm text-slate-600"><?= e((string) $assessment['summary']) ?></p>
                <p class="mt-2 text-xs text-slate-400">Fit <?= e($assessment['fit_score'] ?? '—') ?>/100 · <?= e($assessment['recommendation'] ?? '') ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>
