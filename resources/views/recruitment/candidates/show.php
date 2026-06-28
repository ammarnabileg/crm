<?php
/** @var array<string,mixed> $profile */
/** @var list<array<string,mixed>> $applications */
/** @var list<array<string,mixed>> $notes */
/** @var list<string> $tags */
/** @var bool $canNote */
/** @var bool $canTag */
?>
<div class="mb-6">
    <a href="/candidates" class="text-sm text-indigo-600 hover:underline">&larr; Candidates</a>
    <h1 class="mt-1 text-2xl font-semibold text-slate-900"><?= e($profile['name']) ?></h1>
    <p class="text-sm text-slate-500"><?= e($profile['email']) ?></p>
    <div class="mt-2 flex flex-wrap items-center gap-2">
        <?php foreach ($tags as $tag): ?>
            <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700"><?= e($tag) ?></span>
        <?php endforeach; ?>
        <?php if ($canAi ?? false): ?>
            <form method="post" action="/candidates/<?= e($profile['user_id']) ?>/ai-summary">
                <?= csrf_field() ?>
                <button class="rounded-full bg-slate-900 px-3 py-1 text-xs font-medium text-white hover:bg-slate-700">✦ Generate AI summary</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Applications in this workspace</h2>
            <?php if ($applications === []): ?>
                <p class="text-sm text-slate-400">No applications.</p>
            <?php else: ?>
                <ul class="space-y-1 text-sm">
                    <?php foreach ($applications as $a): ?>
                        <li class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2">
                            <span class="font-medium text-slate-800"><?= e($a['job_title']) ?></span>
                            <span class="text-slate-500"><?= e($a['stage'] ?? $a['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Notes</h2>
            <?php if ($canNote): ?>
                <form method="post" action="/candidates/<?= e($profile['user_id']) ?>/notes" class="mb-4 flex gap-2">
                    <?= csrf_field() ?>
                    <input name="body" required placeholder="Add a private note…" class="grow rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    <button class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add</button>
                </form>
            <?php endif; ?>
            <?php if ($notes === []): ?>
                <p class="text-sm text-slate-400">No notes yet.</p>
            <?php else: ?>
                <ul class="space-y-2 text-sm">
                    <?php foreach ($notes as $n): ?>
                        <li class="rounded-lg bg-slate-50 px-3 py-2">
                            <div class="text-slate-700"><?= e($n['body']) ?></div>
                            <div class="mt-1 text-xs text-slate-400"><?= e($n['author'] ?? 'system') ?> · <?= e($n['created_at']) ?> UTC</div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Tags</h2>
        <?php if ($canTag): ?>
            <form method="post" action="/candidates/<?= e($profile['user_id']) ?>/tags" class="flex gap-2">
                <?= csrf_field() ?>
                <input name="tag" required placeholder="e.g. strong-fit" class="grow rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                <button class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-700">Tag</button>
            </form>
        <?php else: ?>
            <p class="text-sm text-slate-400">You don't have permission to tag.</p>
        <?php endif; ?>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Offers</h2>
        <?php foreach (($offers ?? []) as $offer): ?>
            <div class="mb-2 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                <span><?= e($offer['title'] ?: 'Offer') ?> · <span class="text-slate-500"><?= e($offer['status']) ?></span></span>
                <?php if (($canDecide ?? false) && $offer['status'] === 'sent'): ?>
                    <form method="post" action="/offers/<?= e($offer['id']) ?>/accept">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= e($profile['user_id']) ?>">
                        <button class="rounded-md bg-emerald-600 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-700">Mark accepted → hire</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (($offers ?? []) === []): ?><p class="mb-3 text-sm text-slate-400">No offers yet.</p><?php endif; ?>
        <?php if ($canOffer ?? false): ?>
            <form method="post" action="/candidates/<?= e($profile['user_id']) ?>/offer" class="mt-2 flex gap-2">
                <?= csrf_field() ?>
                <input name="title" placeholder="Offer title" class="grow rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                <button class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Make &amp; send</button>
            </form>
        <?php endif; ?>
    </div>
</div>
