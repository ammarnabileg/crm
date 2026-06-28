<?php
/** @var list<array<string,mixed>> $avatars */
/** @var bool $canManage */
/** @var string|null $status */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Avatars</h1>
    <p class="mt-1 text-sm text-slate-500">AI interviewer personas — give your AI interviews a name, style and voice.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-3">
        <?php if ($avatars === []): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-400 shadow-sm">No avatars yet. Create one to personalise AI interviews.</div>
        <?php else: ?>
            <?php foreach ($avatars as $a): ?>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-lg font-bold text-indigo-700"><?= e(strtoupper(substr((string) $a['name'], 0, 1))) ?></div>
                        <div class="min-w-0 grow">
                            <div class="font-semibold text-slate-800"><?= e($a['name']) ?></div>
                            <div class="text-xs text-slate-400"><span class="capitalize"><?= e($a['persona']) ?></span> · <?= e($a['language']) ?><?= ! empty($a['gender']) ? ' · ' . e($a['gender']) : '' ?></div>
                            <?php if (! empty($a['style_notes'])): ?><p class="mt-1 text-sm text-slate-500"><?= e($a['style_notes']) ?></p><?php endif; ?>
                        </div>
                        <?php if ($canManage): ?>
                            <form method="post" action="/avatars/<?= e($a['id']) ?>/delete" onsubmit="return confirm('Delete this avatar?')">
                                <?= csrf_field() ?>
                                <button class="text-xs font-medium text-rose-600 hover:text-rose-700">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm self-start">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">New avatar</h2>
            <form method="post" action="/avatars" class="space-y-2">
                <?= csrf_field() ?>
                <input name="name" required placeholder="e.g. Sara — Technical" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="persona" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <?php foreach (['professional' => 'Professional', 'friendly' => 'Friendly', 'formal' => 'Formal', 'casual' => 'Casual'] as $v => $l): ?>
                        <option value="<?= $v ?>"><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="flex gap-2">
                    <input name="gender" placeholder="Gender (optional)" class="w-1/2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="language" value="en" class="w-1/2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <input name="image_url" placeholder="Image URL (optional)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <textarea name="style_notes" rows="2" placeholder="Interview style notes…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create avatar</button>
            </form>
        </div>
    <?php endif; ?>
</div>
