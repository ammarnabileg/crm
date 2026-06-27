<?php /** @var list<array<string,mixed>> $items */ ?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Activity</h1>
    <p class="mt-1 text-sm text-slate-500">Everything that happens in this workspace, from the audit log.</p>
</div>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <?php if ($items === []): ?>
        <p class="px-5 py-6 text-sm text-slate-400">No activity yet.</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($items as $item): ?>
                <li class="flex items-center justify-between px-5 py-3 text-sm">
                    <div>
                        <span class="font-mono text-xs text-indigo-600"><?= e($item['action']) ?></span>
                        <?php if (! empty($item['entity_type'])): ?>
                            <span class="text-slate-400">· <?= e($item['entity_type']) ?></span>
                        <?php endif; ?>
                        <div class="text-slate-500">by <?= e($item['actor_name'] ?? 'system') ?></div>
                    </div>
                    <time class="text-xs text-slate-400"><?= e($item['created_at']) ?> UTC</time>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
