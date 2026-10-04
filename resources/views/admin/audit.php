<?php /** @var list<array<string,mixed>> $entries */ ?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Audit Logs</h1>
    <p class="mt-1 text-sm text-slate-500">The platform-wide audit trail across all workspaces.</p>
</div>
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <?php if ($entries === []): ?>
        <p class="px-5 py-6 text-sm text-slate-400">No audit entries yet.</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($entries as $a): ?>
                <li class="flex items-center justify-between px-5 py-3 text-sm">
                    <div>
                        <span class="font-mono text-xs text-indigo-600"><?= e($a['action']) ?></span>
                        <?php if (! empty($a['entity_type'])): ?><span class="text-slate-400">· <?= e($a['entity_type']) ?></span><?php endif; ?>
                        <div class="text-xs text-slate-500"><?= e($a['workspace'] ?? 'platform') ?> · by <?= e($a['actor'] ?? 'system') ?></div>
                    </div>
                    <time class="text-xs text-slate-400"><?= e($a['created_at']) ?> UTC</time>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
