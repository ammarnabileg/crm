<?php
/** @var string $query */
/** @var array{members: list<array<string,mixed>>, roles: list<array<string,mixed>>} $results */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Search</h1>
    <p class="mt-1 text-sm text-slate-500">Unified search across this workspace.</p>
</div>

<form method="get" action="/search" class="mb-6 max-w-xl">
    <input name="q" value="<?= e($query) ?>" autofocus placeholder="Search members, roles…" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:outline-none">
</form>

<?php if ($query !== ''): ?>
    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Members (<?= count($results['members']) ?>)</h2>
            <?php if ($results['members'] === []): ?>
                <p class="text-sm text-slate-400">No matching members.</p>
            <?php else: ?>
                <ul class="space-y-1 text-sm">
                    <?php foreach ($results['members'] as $m): ?>
                        <li class="rounded-md bg-slate-50 px-3 py-2"><span class="font-medium text-slate-800"><?= e($m['name']) ?></span> <span class="text-slate-500"><?= e($m['email']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Roles (<?= count($results['roles']) ?>)</h2>
            <?php if ($results['roles'] === []): ?>
                <p class="text-sm text-slate-400">No matching roles.</p>
            <?php else: ?>
                <ul class="space-y-1 text-sm">
                    <?php foreach ($results['roles'] as $r): ?>
                        <li class="rounded-md bg-slate-50 px-3 py-2 font-medium text-slate-800"><?= e($r['name']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <p class="text-sm text-slate-400">Type a query to search.</p>
<?php endif; ?>
