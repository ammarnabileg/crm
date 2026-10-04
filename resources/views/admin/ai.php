<?php
/** @var array{workspaces: list<array<string,mixed>>, totals: list<array<string,mixed>>, configured: int} $overview */
$workspaces = $overview['workspaces'];
$totals = $overview['totals'];
$configured = $overview['configured'];
$money = static fn (int $cents): string => '$' . number_format($cents / 100, 2);
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">AI Providers</h1>
    <p class="mt-1 text-sm text-slate-500">Platform-wide oversight of AI adoption and usage. Keys are added per workspace and stay private — only a masked hint is shown here.</p>
</div>

<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="text-xs uppercase tracking-wide text-slate-400">Workspaces with AI keys</div>
        <div class="mt-1 text-2xl font-bold text-slate-900"><?= e($configured) ?> <span class="text-base font-medium text-slate-400">/ <?= e(count($workspaces)) ?></span></div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="text-xs uppercase tracking-wide text-slate-400">Total AI runs</div>
        <div class="mt-1 text-2xl font-bold text-slate-900"><?= e(array_sum(array_map(static fn (array $t): int => (int) $t['runs'], $totals))) ?></div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="text-xs uppercase tracking-wide text-slate-400">Total AI spend</div>
        <div class="mt-1 text-2xl font-bold text-slate-900"><?= e($money((int) array_sum(array_map(static fn (array $t): int => (int) $t['cost_cents'], $totals)))) ?></div>
    </div>
</div>

<?php if ($totals !== []): ?>
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Usage by provider</h2>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($totals as $t): ?>
                <span class="rounded-full bg-slate-50 px-3 py-1 text-xs text-slate-600">
                    <span class="font-medium text-slate-800"><?= e($t['provider'] ?? '—') ?></span>
                    · <?= e($t['runs']) ?> runs · <?= e(number_format((int) $t['tokens'])) ?> tokens · <?= e($money((int) $t['cost_cents'])) ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
            <tr><th class="px-5 py-3">Workspace</th><th class="px-5 py-3">Owner</th><th class="px-5 py-3">Default provider</th><th class="px-5 py-3">Keys configured</th><th class="px-5 py-3 text-right">Runs</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($workspaces as $w): ?>
                <?php $hasKeys = (int) ($w['key_count'] ?? 0) > 0; ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-medium text-slate-800"><?= e($w['name']) ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($w['owner_name'] ?? '—') ?></td>
                    <td class="px-5 py-3 text-slate-600">
                        <?php if (! empty($w['default_provider'])): ?>
                            <?= e($w['default_provider']) ?><?php if (! empty($w['model'])): ?> <span class="text-xs text-slate-400">· <?= e($w['model']) ?></span><?php endif; ?>
                        <?php else: ?><span class="text-slate-300">—</span><?php endif; ?>
                    </td>
                    <td class="px-5 py-3">
                        <?php if ($hasKeys): ?>
                            <span class="text-slate-600"><?= e($w['keys_configured']) ?></span>
                        <?php else: ?>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-400">none</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-right text-slate-600"><?= e($w['runs']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($workspaces === []): ?>
                <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-slate-400">No workspaces yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
