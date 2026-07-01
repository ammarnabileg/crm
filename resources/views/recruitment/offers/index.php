<?php
/** @var list<array<string,mixed>> $offers */
/** @var bool $canManage */
/** @var string|null $status */
/** @var string|null $error */

$badge = static fn (string $s): string => match ($s) {
    'accepted' => 'bg-emerald-100 text-emerald-700',
    'sent' => 'bg-amber-100 text-amber-700',
    'proposed' => 'bg-indigo-100 text-indigo-700',
    'declined', 'revoked' => 'bg-rose-100 text-rose-700',
    default => 'bg-slate-100 text-slate-600',
};
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Offers</h1>
    <p class="mt-1 text-sm text-slate-500">Every offer across this workspace, including candidate counter-proposals.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <?php if ($offers === []): ?>
        <p class="px-5 py-8 text-center text-sm text-slate-400">No offers yet. Make one from a candidate profile.</p>
    <?php else: ?>
        <table class="w-full text-sm">
            <thead class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400">
                <tr><th class="px-5 py-3">Candidate</th><th class="px-5 py-3">Role</th><th class="px-5 py-3">Salary</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($offers as $o): ?>
                    <tr>
                        <td class="px-5 py-3">
                            <a href="/candidates/<?= e($o['candidate_user_id']) ?>" class="font-medium text-indigo-600 hover:underline"><?= e($o['candidate_name']) ?></a>
                            <?php if ((string) ($o['proposed_by'] ?? '') === 'candidate'): ?><span class="ml-1 rounded bg-indigo-50 px-1.5 py-0.5 text-xs font-medium text-indigo-600">counter</span><?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-slate-600"><?= e($o['title'] ?: $o['job_title']) ?></td>
                        <td class="px-5 py-3 text-slate-600"><?= $o['salary'] !== null ? e(number_format((float) $o['salary'])) . ' ' . e($o['currency']) : '—' ?></td>
                        <td class="px-5 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $badge((string) $o['status']) ?>"><?= e($o['status']) ?></span></td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <a href="/offers/<?= e($o['id']) ?>/print" class="text-xs font-medium text-slate-500 hover:text-slate-700">Print</a>
                                <?php if ($canManage): ?>
                                    <?php if ((string) $o['status'] === 'draft'): ?>
                                        <form method="post" action="/offers/<?= e($o['id']) ?>/send"><?= csrf_field() ?><button class="rounded bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-indigo-700">Send</button></form>
                                    <?php endif; ?>
                                    <?php if (in_array((string) $o['status'], ['draft', 'sent', 'proposed'], true)): ?>
                                        <form method="post" action="/offers/<?= e($o['id']) ?>/withdraw" onsubmit="return confirm('Withdraw this offer?');"><?= csrf_field() ?><button class="rounded border border-rose-200 px-2.5 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50">Withdraw</button></form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
