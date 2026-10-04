<?php
/**
 * @var array{programs:int,published:int,enrollments:int,completed:int,in_progress:int,not_started:int,completion_rate:int,certificates:int,active_learners:int} $overview
 * @var list<array<string,mixed>> $perProgram
 * @var list<array<string,mixed>> $topLearners
 * @var list<array<string,mixed>> $instructorPrograms
 * @var string|null $status
 */
$card = static fn (string $label, $value, string $color = 'slate'): string =>
    '<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">'
    . '<div class="text-2xl font-semibold text-' . $color . '-700">' . e((string) $value) . '</div>'
    . '<div class="text-xs text-slate-500">' . e($label) . '</div></div>';
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Learning Analytics</h1>
        <p class="mt-1 text-sm text-slate-500">Adoption, completion and learner progress across your programs.</p>
    </div>
    <a href="/learning" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">← Programs</a>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    <?= $card('Programs', $overview['programs']) ?>
    <?= $card('Published', $overview['published'], 'emerald') ?>
    <?= $card('Enrollments', $overview['enrollments'], 'indigo') ?>
    <?= $card('Completion rate', $overview['completion_rate'] . '%', 'emerald') ?>
    <?= $card('Certificates', $overview['certificates'], 'violet') ?>
    <?= $card('Active learners', $overview['active_learners'], 'indigo') ?>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">By program</h2>
            <?php if ($perProgram === []): ?><p class="text-xs text-slate-400">No programs yet.</p><?php else: ?>
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs text-slate-400"><th class="pb-2">Program</th><th class="pb-2">Status</th><th class="pb-2 text-right">Enrolled</th><th class="pb-2 text-right">Completed</th><th class="pb-2 text-right">Avg %</th></tr></thead>
                    <tbody>
                        <?php foreach ($perProgram as $p): ?>
                            <tr class="border-t border-slate-50">
                                <td class="py-2"><a href="/learning/<?= e($p['id']) ?>" class="text-slate-700 hover:text-indigo-700"><?= e($p['title']) ?></a></td>
                                <td class="py-2 text-xs text-slate-500"><?= e($p['status']) ?></td>
                                <td class="py-2 text-right text-slate-600"><?= (int) $p['enrolled'] ?></td>
                                <td class="py-2 text-right text-emerald-700"><?= (int) $p['completed'] ?></td>
                                <td class="py-2 text-right font-medium text-slate-700"><?= (int) $p['avg_percent'] ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if (! empty($instructorPrograms)): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-900">My programs (instructor)</h2>
                <div class="space-y-2">
                    <?php foreach ($instructorPrograms as $p): ?>
                        <div class="flex items-center justify-between text-sm">
                            <a href="/learning/<?= e($p['id']) ?>" class="text-slate-700 hover:text-indigo-700"><?= e($p['title']) ?></a>
                            <span class="text-xs text-slate-500"><?= (int) $p['enrolled'] ?> enrolled · <?= (int) $p['completed'] ?> done · <?= (int) $p['avg_percent'] ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm self-start">
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Top learners</h2>
        <?php if ($topLearners === []): ?><p class="text-xs text-slate-400">No learners yet.</p><?php else: ?>
            <div class="space-y-2">
                <?php foreach ($topLearners as $i => $l): ?>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-600"><span class="text-slate-400"><?= $i + 1 ?>.</span> <?= e($l['name']) ?></span>
                        <span class="text-xs text-emerald-700"><?= (int) $l['completed'] ?> done</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
