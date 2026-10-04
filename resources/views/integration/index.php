<?php
/** @var list<array<string,mixed>> $tokens */
/** @var list<array<string,mixed>> $endpoints */
/** @var list<array<string,mixed>> $deliveries */
/** @var list<string> $events */
/** @var bool $canManageTokens */
/** @var bool $canManageWebhooks */
/** @var string|null $newToken */
/** @var string|null $newSecret */
/** @var string|null $status */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Developer</h1>
    <p class="mt-1 text-sm text-slate-500">API tokens for the gateway, and outbound webhooks that fire on workspace events. The same permissions that govern the UI govern the API.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<?php if ($newToken): ?>
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
        <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">New API token — copy it now</div>
        <code class="mt-1 block break-all font-mono text-sm text-amber-900"><?= e($newToken) ?></code>
    </div>
<?php endif; ?>
<?php if ($newSecret): ?>
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
        <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Webhook signing secret — copy it now</div>
        <code class="mt-1 block break-all font-mono text-sm text-amber-900"><?= e($newSecret) ?></code>
    </div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-2">
    <!-- API tokens -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">API tokens</h2>
        <?php if ($tokens === []): ?>
            <p class="px-5 py-6 text-sm text-slate-400">No tokens yet.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($tokens as $t): ?>
                    <li class="flex items-center justify-between px-5 py-3 text-sm">
                        <div>
                            <div class="font-medium text-slate-800"><?= e($t['name']) ?></div>
                            <div class="font-mono text-xs text-slate-400"><?= e($t['token_prefix']) ?>…<?= e($t['last_four']) ?>
                                <?php if (! empty($t['last_used_at'])): ?>· last used <?= e($t['last_used_at']) ?><?php else: ?>· never used<?php endif; ?>
                            </div>
                        </div>
                        <?php if (! empty($t['revoked_at'])): ?>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">Revoked</span>
                        <?php elseif ($canManageTokens): ?>
                            <form method="post" action="/integrations/tokens/<?= e($t['id']) ?>/revoke" onsubmit="return confirm('Revoke this token?')">
                                <?= csrf_field() ?>
                                <button class="text-xs font-medium text-rose-600 hover:text-rose-700">Revoke</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($canManageTokens): ?>
            <form method="post" action="/integrations/tokens" class="border-t border-slate-100 p-5 space-y-2">
                <?= csrf_field() ?>
                <input name="name" required placeholder="Token name (e.g. CI pipeline)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create token</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Webhooks -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Webhook endpoints</h2>
        <?php if ($endpoints === []): ?>
            <p class="px-5 py-6 text-sm text-slate-400">No endpoints yet.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($endpoints as $e): ?>
                    <li class="px-5 py-3 text-sm">
                        <div class="flex items-center justify-between">
                            <div class="min-w-0">
                                <div class="truncate font-medium text-slate-800"><?= e($e['url']) ?></div>
                                <div class="text-xs text-slate-400"><?= e(implode(', ', (array) $e['events'])) ?></div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <?php if ((int) $e['enabled'] === 1): ?>
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Enabled</span>
                                <?php else: ?>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">Disabled</span>
                                <?php endif; ?>
                                <?php if ($canManageWebhooks): ?>
                                    <form method="post" action="/integrations/webhooks/<?= e($e['id']) ?>/toggle">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="enabled" value="<?= (int) $e['enabled'] === 1 ? '0' : '1' ?>">
                                        <button class="text-xs font-medium text-slate-500 hover:text-slate-700"><?= (int) $e['enabled'] === 1 ? 'Disable' : 'Enable' ?></button>
                                    </form>
                                    <form method="post" action="/integrations/webhooks/<?= e($e['id']) ?>/delete" onsubmit="return confirm('Delete this endpoint?')">
                                        <?= csrf_field() ?>
                                        <button class="text-xs font-medium text-rose-600 hover:text-rose-700">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ((int) ($e['failure_count'] ?? 0) > 0): ?>
                            <div class="mt-1 text-xs text-rose-500"><?= e($e['failure_count']) ?> recent failure(s)</div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($canManageWebhooks): ?>
            <form method="post" action="/integrations/webhooks" class="border-t border-slate-100 p-5 space-y-2">
                <?= csrf_field() ?>
                <input name="url" required type="url" placeholder="https://example.com/hooks/hahireai" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <div class="space-y-1">
                    <?php foreach ($events as $ev): ?>
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="events[]" value="<?= e($ev) ?>" checked class="rounded border-slate-300">
                            <span class="font-mono text-xs"><?= e($ev) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add endpoint</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Recent deliveries -->
<div class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
    <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Recent deliveries</h2>
    <?php if ($deliveries === []): ?>
        <p class="px-5 py-6 text-sm text-slate-400">No deliveries yet. They appear here when a subscribed event fires.</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($deliveries as $d): ?>
                <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                    <div>
                        <span class="font-mono text-xs text-indigo-600"><?= e($d['event']) ?></span>
                        <span class="text-slate-400">· HTTP <?= e($d['response_status'] ?? '—') ?></span>
                    </div>
                    <div class="text-right">
                        <?php $cls = (string) $d['status'] === 'delivered' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'; ?>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $cls ?>"><?= e($d['status']) ?></span>
                        <div class="mt-0.5 text-xs text-slate-400"><?= e($d['created_at']) ?> UTC</div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
