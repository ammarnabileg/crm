<?php
/** @var list<array<string,mixed>> $candidates */
/** @var array<string,array{label:string,weight:int}> $skillCatalog */
/** @var string $question */
/** @var array{answer:string,provider:string}|null $answer */
/** @var list<string> $ids */
/** @var bool $canAsk */
$band = ['strong' => 'Strong', 'suitable' => 'Suitable', 'maybe' => 'Maybe', 'unsuitable' => 'Not suitable'];
?>
<div class="mb-6">
    <a href="/candidates" class="text-sm text-indigo-600 hover:underline">&larr; Candidates</a>
    <h1 class="mt-1 text-2xl font-semibold text-slate-900">Compare candidates</h1>
    <p class="mt-1 text-sm text-slate-500">Side-by-side AI assessments. Ask the AI a question across them — it's advisory; you decide.</p>
</div>

<?php if ($candidates === []): ?>
    <div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-400 shadow-sm">Select candidates from the list to compare them here.</div>
<?php else: ?>
    <?php if ($canAsk): ?>
        <form method="get" action="/candidates/compare" class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <?php foreach ($ids as $id): ?><input type="hidden" name="ids[]" value="<?= e($id) ?>"><?php endforeach; ?>
            <label class="mb-1 block text-xs font-medium text-slate-500">Ask the AI about these candidates</label>
            <div class="flex gap-2">
                <input name="q" value="<?= e($question) ?>" placeholder="e.g. Who is the best fit for a people-facing role?" class="grow rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Ask AI</button>
            </div>
            <?php if ($answer !== null): ?>
                <div class="mt-3 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                    <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-indigo-500">AI answer (<?= e($answer['provider']) ?>) — advisory</div>
                    <?= e($answer['answer']) ?>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>

    <div class="flex gap-4 overflow-x-auto pb-4">
        <?php foreach ($candidates as $c): ?>
            <?php $a = $c['assessment']; ?>
            <div class="w-72 shrink-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <a href="/candidates/<?= e($c['user_id']) ?>" class="font-semibold text-indigo-600 hover:underline"><?= e($c['name']) ?></a>
                <?php if ($a === null): ?>
                    <p class="mt-2 text-sm text-slate-400">No AI assessment yet.</p>
                <?php else: ?>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="text-xl font-bold text-slate-900"><?= e($a['fit_score']) ?>/100</span>
                        <span class="text-xs font-medium text-slate-500"><?= e($band[(string) $a['recommendation']] ?? '') ?></span>
                    </div>
                    <div class="mt-3 space-y-1">
                        <?php foreach (($a['skills'] ?? []) as $key => $s): ?>
                            <?php $score = (int) ($s['score'] ?? 0); $cls = $score >= 75 ? 'bg-emerald-500' : ($score >= 55 ? 'bg-amber-500' : 'bg-rose-400'); ?>
                            <div class="flex items-center gap-1.5 text-xs">
                                <div class="w-28 shrink-0 truncate text-slate-500"><?= e($skillCatalog[$key]['label'] ?? $key) ?></div>
                                <div class="h-1.5 grow rounded bg-slate-100"><div class="h-1.5 rounded <?= $cls ?>" style="width: <?= $score ?>%"></div></div>
                                <div class="w-6 shrink-0 text-right text-slate-600"><?= $score ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php $b = $a['behavior'] ?? []; ?>
                    <div class="mt-3 text-xs text-slate-500">DISC <?= e($b['disc'] ?? '—') ?> · <?= e($b['leadership_style'] ?? '') ?></div>
                    <div class="mt-1 text-xs text-emerald-700">+ <?= e(implode(', ', (array) ($a['strengths'] ?? []))) ?></div>
                    <div class="text-xs text-rose-600">– <?= e(implode(', ', (array) ($a['weaknesses'] ?? []))) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
