<?php
/**
 * @var list<array<string,mixed>> $programs
 * @var array{status:string,q:string,category:string} $filters
 * @var array<string,string> $statuses
 * @var array<string,string> $difficulties
 * @var bool $canManage
 * @var string|null $status
 */
$badge = [
    'draft' => 'bg-slate-100 text-slate-600',
    'published' => 'bg-emerald-100 text-emerald-700',
    'archived' => 'bg-amber-100 text-amber-700',
];
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Learning</h1>
        <p class="mt-1 text-sm text-slate-500">Training, onboarding &amp; development programs for your team.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="/learning/analytics" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Analytics</a>
        <a href="/learning-paths" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Paths</a>
        <a href="/my-learning" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">My Learning →</a>
    </div>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-4">
        <form method="get" action="/learning" class="flex flex-wrap items-end gap-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Status</label>
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All</option>
                    <?php foreach ($statuses as $v => $l): ?>
                        <option value="<?= $v ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grow">
                <label class="mb-1 block text-xs font-medium text-slate-600">Search</label>
                <input name="q" value="<?= e($filters['q']) ?>" placeholder="Title or summary…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Filter</button>
        </form>

        <?php if ($programs === []): ?>
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center text-sm text-slate-400 shadow-sm">
                No programs yet.<?php if ($canManage): ?> Create your first one to start building.<?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid gap-4 sm:grid-cols-2">
                <?php foreach ($programs as $p): ?>
                    <a href="/learning/<?= e($p['id']) ?>" class="group block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow">
                        <div class="flex items-center justify-between gap-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $badge[(string) $p['status']] ?? 'bg-slate-100 text-slate-600' ?>"><?= e(ucfirst((string) $p['status'])) ?></span>
                            <span class="text-xs text-slate-400"><?= e($difficulties[(string) $p['difficulty']] ?? ucfirst((string) $p['difficulty'])) ?></span>
                        </div>
                        <h3 class="mt-3 font-semibold text-slate-900 group-hover:text-indigo-700"><?= e($p['title']) ?></h3>
                        <?php if (! empty($p['summary'])): ?><p class="mt-1 line-clamp-2 text-sm text-slate-500"><?= e($p['summary']) ?></p><?php endif; ?>
                        <?php if (! empty($p['tags'])): ?>
                            <div class="mt-3 flex flex-wrap gap-1">
                                <?php foreach (array_slice((array) $p['tags'], 0, 4) as $tag): ?>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500"><?= e($tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="mt-4 flex items-center gap-4 text-xs text-slate-400">
                            <span><?= (int) ($p['items_count'] ?? 0) ?> items</span>
                            <span><?= (int) ($p['enrolled_count'] ?? 0) ?> enrolled</span>
                            <?php if ((int) ($p['estimated_minutes'] ?? 0) > 0): ?><span><?= (int) $p['estimated_minutes'] ?> min</span><?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
        <div class="self-start rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">New program</h2>
            <form method="post" action="/learning" class="space-y-2">
                <?= csrf_field() ?>
                <input name="title" required placeholder="e.g. Software Developer Onboarding" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <textarea name="summary" rows="2" placeholder="A short summary" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                <input name="category" placeholder="Category (e.g. Onboarding)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <div class="flex gap-2">
                    <select name="difficulty" class="w-1/2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <?php foreach ($difficulties as $v => $l): ?><option value="<?= $v ?>"><?= e($l) ?></option><?php endforeach; ?>
                    </select>
                    <input name="estimated_minutes" type="number" min="0" placeholder="Minutes" class="w-1/2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <input name="tags" placeholder="Tags, comma separated" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create program</button>
            </form>
            <p class="mt-3 text-xs text-slate-400">You'll add sections &amp; content next.</p>
        </div>
    <?php endif; ?>
</div>
