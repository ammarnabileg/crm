<?php
/** @var list<array<string,mixed>> $candidates */
/** @var bool $searching */
/** @var array{min_score:int,recommendation:string,skill:string,skill_min:int} $filters */
/** @var array<string,array{label:string,weight:int}> $skills */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Candidates</h1>
    <p class="mt-1 text-sm text-slate-500">People who interacted with this workspace. You only ever see your workspace's view.</p>
</div>

<!-- Advanced search by AI assessment -->
<form method="get" action="/candidates" class="mb-4 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Min score</label>
        <input type="number" name="min_score" min="0" max="100" value="<?= e($filters['min_score'] ?: '') ?>" class="w-24 rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="0-100">
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Recommendation</label>
        <select name="recommendation" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Any</option>
            <?php foreach (['strong' => 'Strong', 'suitable' => 'Suitable', 'maybe' => 'Maybe', 'unsuitable' => 'Not suitable'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= $filters['recommendation'] === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Skill</label>
        <select name="skill" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Any</option>
            <?php foreach ($skills as $key => $meta): ?>
                <option value="<?= e($key) ?>" <?= $filters['skill'] === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Skill ≥</label>
        <input type="number" name="skill_min" min="0" max="100" value="<?= e($filters['skill_min'] ?: '') ?>" class="w-20 rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="0">
    </div>
    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Search</button>
    <?php if ($searching): ?><a href="/candidates" class="text-sm text-slate-500 hover:underline">Clear</a><?php endif; ?>
</form>

<form method="get" action="/candidates/compare" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <?php if ($candidates === []): ?>
        <p class="px-5 py-8 text-center text-sm text-slate-400"><?= $searching ? 'No candidates match these criteria.' : 'No candidates yet. They appear here when someone applies.' ?></p>
    <?php else: ?>
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-2">
            <span class="text-xs text-slate-400">Select candidates to compare side-by-side.</span>
            <button class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">Compare selected</button>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-5 py-3 w-8"></th>
                    <th class="px-5 py-3">Name</th>
                    <th class="px-5 py-3">Email</th>
                    <th class="px-5 py-3"><?= $searching ? 'AI score' : 'Applications' ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($candidates as $c): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3"><input type="checkbox" name="ids[]" value="<?= e($c['user_id']) ?>" class="rounded border-slate-300"></td>
                        <td class="px-5 py-3"><a href="/candidates/<?= e($c['user_id']) ?>" class="font-medium text-indigo-600 hover:underline"><?= e($c['name']) ?></a></td>
                        <td class="px-5 py-3 text-slate-600"><?= e($c['email']) ?></td>
                        <td class="px-5 py-3 text-slate-600">
                            <?php if ($searching): ?>
                                <span class="font-semibold text-slate-800"><?= e($c['fit_score']) ?>/100</span>
                                <span class="text-xs text-slate-400">· <?= e($c['recommendation']) ?></span>
                            <?php else: ?>
                                <?= e($c['applications']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</form>
