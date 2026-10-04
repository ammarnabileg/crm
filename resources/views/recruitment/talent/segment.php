<?php
/** @var array<string,mixed> $segment */
/** @var list<array<string,mixed>> $rules */
/** @var list<array{user_id:string,name:string,email:string,reasons:list<string>,reason:string}> $matches */
/** @var list<array<string,mixed>> $pools */
/** @var array<string, array{operator:string,requiresValue:bool,label:string}> $fields */
/** @var bool $canManage */
/** @var string|null $status */
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900"><?= e($segment['name']) ?></h1>
        <p class="mt-1 text-sm text-slate-500">Match <?= $segment['match_type'] === 'any' ? 'ANY rule (OR)' : 'ALL rules (AND)' ?> · <?= count($matches) ?> candidate(s) match now.</p>
    </div>
    <a href="/talent-pool/segments" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50">← Segments</a>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <!-- Results + bulk-add -->
    <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white shadow-sm">
        <?php if ($matches === []): ?>
            <p class="px-5 py-6 text-sm text-slate-400">No candidates match this segment yet.</p>
        <?php else: ?>
            <form method="post" action="/talent-pool/segments/<?= e($segment['id']) ?>/bulk-add" class="p-5">
                <?= csrf_field() ?>
                <ul class="mb-4 divide-y divide-slate-100">
                    <?php foreach ($matches as $m): ?>
                        <li class="flex items-center justify-between py-2 text-sm">
                            <label class="flex items-center gap-2">
                                <?php if ($canManage): ?><input type="checkbox" name="candidate_user_ids[]" value="<?= e($m['user_id']) ?>" checked class="rounded border-slate-300"><?php endif; ?>
                                <a href="/candidates/<?= e($m['user_id']) ?>" class="font-medium text-indigo-600 hover:underline"><?= e($m['name']) ?></a>
                                <span class="text-xs text-slate-400"><?= e($m['email']) ?></span>
                            </label>
                            <span class="text-xs text-slate-400"><?= e($m['reason']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($canManage && $pools !== []): ?>
                    <div class="flex items-center gap-2">
                        <select name="pool_id" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                            <?php foreach ($pools as $p): ?><option value="<?= e($p['id']) ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
                        </select>
                        <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700">Add selected to pool</button>
                    </div>
                <?php elseif ($canManage): ?>
                    <p class="text-xs text-slate-400">Create a pool in the Talent Pool to save these candidates.</p>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

    <!-- Rule editor -->
    <?php if ($canManage): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm self-start" data-segment-builder>
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Edit rules</h2>
            <form method="post" action="/talent-pool/segments/<?= e($segment['id']) ?>" class="space-y-3">
                <?= csrf_field() ?>
                <input name="name" required value="<?= e($segment['name']) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <label class="block text-xs font-medium text-slate-500">Match
                    <select name="match_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="all" <?= $segment['match_type'] === 'all' ? 'selected' : '' ?>>ALL rules (AND)</option>
                        <option value="any" <?= $segment['match_type'] === 'any' ? 'selected' : '' ?>>ANY rule (OR)</option>
                    </select>
                </label>

                <div class="space-y-2" data-rules>
                    <?php $rows = $rules !== [] ? $rules : [['field' => '', 'value' => '']]; ?>
                    <?php foreach ($rows as $rule): ?>
                        <div class="flex items-center gap-2" data-rule-row>
                            <select name="rule_field[]" class="w-1/2 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                <option value="">—</option>
                                <?php foreach ($fields as $key => $meta): ?>
                                    <option value="<?= e($key) ?>" <?= (string) ($rule['field'] ?? '') === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input name="rule_value[]" value="<?= e((string) ($rule['value'] ?? '')) ?>" placeholder="value" class="w-1/2 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" data-add-rule class="text-xs font-medium text-indigo-600 hover:underline">+ Add rule</button>

                <button class="block w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save changes</button>
            </form>
            <form method="post" action="/talent-pool/segments/<?= e($segment['id']) ?>/delete" class="mt-3" onsubmit="return confirm('Delete this segment?');">
                <?= csrf_field() ?>
                <button class="text-xs font-medium text-rose-600 hover:underline">Delete segment</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
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
