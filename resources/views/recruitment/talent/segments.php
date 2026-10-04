<?php
/** @var list<array<string,mixed>> $segments */
/** @var array<string, array{operator:string,requiresValue:bool,label:string}> $fields */
/** @var bool $canManage */
/** @var string|null $status */
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Smart Segments</h1>
        <p class="mt-1 text-sm text-slate-500">Named, saved candidate filters — a smarter alternative to one-shot search. Combine criteria with AND or OR.</p>
    </div>
    <a href="/talent-pool" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50">← Talent Pool</a>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white shadow-sm">
        <?php if ($segments === []): ?>
            <p class="px-5 py-6 text-sm text-slate-400">No segments yet. Build one on the right to save a reusable candidate filter.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($segments as $s): ?>
                    <li class="flex items-center justify-between px-5 py-3 text-sm">
                        <div>
                            <a href="/talent-pool/segments/<?= e($s['id']) ?>" class="font-medium text-indigo-600 hover:underline"><?= e($s['name']) ?></a>
                            <div class="text-xs text-slate-400">Match <?= $s['match_type'] === 'any' ? 'ANY' : 'ALL' ?> · <?= (int) $s['rules'] ?> rule(s)</div>
                        </div>
                        <a href="/talent-pool/segments/<?= e($s['id']) ?>" class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Run →</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm self-start" data-segment-builder>
            <h2 class="mb-3 text-sm font-semibold text-slate-900">New segment</h2>
            <form method="post" action="/talent-pool/segments" class="space-y-3">
                <?= csrf_field() ?>
                <input name="name" required placeholder="e.g. Senior React — English" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <label class="block text-xs font-medium text-slate-500">Match
                    <select name="match_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="all">ALL rules (AND)</option>
                        <option value="any">ANY rule (OR)</option>
                    </select>
                </label>

                <div class="space-y-2" data-rules>
                    <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="flex items-center gap-2" data-rule-row>
                            <select name="rule_field[]" class="w-1/2 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                <option value="">—</option>
                                <?php foreach ($fields as $key => $meta): ?>
                                    <option value="<?= e($key) ?>"><?= e($meta['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input name="rule_value[]" placeholder="value" class="w-1/2 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                        </div>
                    <?php endfor; ?>
                </div>
                <button type="button" data-add-rule class="text-xs font-medium text-indigo-600 hover:underline">+ Add rule</button>

                <button class="block w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save segment</button>
                <p class="text-xs text-slate-400">Fields: Skill, Language, Score (min), Last interview (months), Available, Status, Seniority. Leave a value blank for “Available”.</p>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
// Progressive enhancement: clone the last rule row. Works without JS too — three
// rows are rendered by default and empty rows are ignored server-side.
document.querySelectorAll('[data-segment-builder]').forEach(function (builder) {
    var addBtn = builder.querySelector('[data-add-rule]');
    var rules = builder.querySelector('[data-rules]');
    if (!addBtn || !rules) { return; }
    addBtn.addEventListener('click', function () {
        var rows = rules.querySelectorAll('[data-rule-row]');
        var clone = rows[rows.length - 1].cloneNode(true);
        clone.querySelectorAll('select, input').forEach(function (el) { el.value = ''; });
        rules.appendChild(clone);
    });
});
</script>
