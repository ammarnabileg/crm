<?php
/**
 * @var array<string,mixed> $program
 * @var list<array<string,mixed>> $structure
 * @var array<string,mixed>|null $enrollment
 * @var array<string,string> $itemStatuses   item_id => status
 * @var list<array<string,mixed>> $todos
 * @var list<array<string,mixed>> $comments
 * @var array<string,array{label:string,icon:string,hint:string}> $itemTypes
 * @var string $currentUserId
 * @var string|null $status
 */
$pid = (string) $program['id'];
$pct = (int) ($enrollment['progress_percent'] ?? 0);
$done = (string) ($enrollment['status'] ?? '') === 'completed';
?>
<div class="mb-4"><a href="/my-learning" class="text-sm text-slate-500 hover:text-slate-700">← My Learning</a></div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<div class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-2xl font-semibold text-slate-900"><?= e($program['title']) ?></h1>
    <?php if (! empty($program['summary'])): ?><p class="mt-1 text-sm text-slate-500"><?= e($program['summary']) ?></p><?php endif; ?>
    <div class="mt-4 flex items-center gap-3">
        <div class="h-2 grow rounded-full bg-slate-100"><div class="h-2 rounded-full <?= $done ? 'bg-emerald-500' : 'bg-indigo-500' ?>" style="width:<?= max(2, $pct) ?>%"></div></div>
        <span class="text-sm font-medium text-slate-700"><?= $pct ?>%</span>
        <?php if ($done): ?><span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Completed 🎉</span><?php endif; ?>
    </div>
</div>

<div class="space-y-5">
    <?php foreach ($structure as $si => $section): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900"><span class="text-slate-400"><?= $si + 1 ?>.</span> <?= e($section['title']) ?></h2>
            <?php if (! empty($section['description'])): ?><p class="mb-3 text-sm text-slate-500"><?= e($section['description']) ?></p><?php endif; ?>
            <div class="space-y-2">
                <?php foreach ((array) $section['items'] as $item): ?>
                    <?php
                    $iid = (string) $item['id'];
                    $type = (string) $item['type'];
                    $meta = $itemTypes[$type] ?? ['label' => $type];
                    $itemDone = ($itemStatuses[$iid] ?? '') === 'completed';
                    ?>
                    <div class="rounded-xl border <?= $itemDone ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-100' ?> p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <span class="text-xs font-medium uppercase tracking-wide text-indigo-500"><?= e($meta['label']) ?></span>
                                <span class="ml-1 text-sm font-medium text-slate-800 <?= $itemDone ? 'line-through' : '' ?>"><?= e($item['title']) ?></span>
                                <?php if ((int) $item['is_required'] === 0): ?><span class="ml-1 text-[11px] text-slate-400">optional</span><?php endif; ?>
                                <?php if (! empty($item['body'])): ?><p class="mt-1 whitespace-pre-line text-sm text-slate-600"><?= e($item['body']) ?></p><?php endif; ?>
                                <?php if (! empty($item['url'])): ?><a href="<?= e($item['url']) ?>" target="_blank" rel="noopener" class="mt-1 inline-block text-xs text-indigo-600 hover:underline">Open resource ↗</a><?php endif; ?>
                            </div>
                            <form method="post" action="/my-learning/<?= e($pid) ?>/items/<?= e($iid) ?>" class="shrink-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status" value="<?= $itemDone ? 'not_started' : 'completed' ?>">
                                <button class="rounded-lg px-3 py-1.5 text-xs font-semibold <?= $itemDone ? 'border border-slate-300 bg-white text-slate-600 hover:bg-slate-50' : 'bg-emerald-600 text-white hover:bg-emerald-700' ?>">
                                    <?= $itemDone ? 'Undo' : 'Mark done' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ((array) $section['items'] === []): ?><p class="text-xs text-slate-400">No content in this section.</p><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($todos !== []): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">To-dos</h2>
            <div class="space-y-2">
                <?php foreach ($todos as $todo): ?>
                    <?php
                    $tdone = (string) $todo['status'] === 'done';
                    $isMine = (string) ($todo['assignee_user_id'] ?? '') === $currentUserId;
                    $managerOnly = (string) $todo['completion_mode'] === 'manager';
                    ?>
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 px-3 py-2 <?= $tdone ? 'opacity-60' : '' ?>">
                        <div>
                            <span class="text-sm text-slate-700 <?= $tdone ? 'line-through' : '' ?>"><?= e($todo['title']) ?></span>
                            <?php if ($managerOnly): ?><span class="ml-1 rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] text-amber-600">manager completes</span><?php endif; ?>
                        </div>
                        <?php if (! $tdone && $isMine && ! $managerOnly): ?>
                            <form method="post" action="/learning/<?= e($pid) ?>/todos/<?= e($todo['id']) ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="done"><button class="text-xs font-medium text-emerald-600 hover:text-emerald-700">Mark done</button></form>
                        <?php elseif ($tdone): ?>
                            <span class="text-xs text-emerald-600">Done</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Questions / discussion -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Questions &amp; discussion</h2>
        <?php foreach ($comments as $c): ?>
            <div class="mb-2 rounded-xl border border-slate-100 p-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-slate-700"><?= e($c['author_name'] ?? 'Someone') ?></span>
                    <span class="text-xs text-slate-400"><?= e(substr((string) $c['created_at'], 0, 16)) ?></span>
                </div>
                <p class="mt-1 whitespace-pre-line text-sm text-slate-600"><?= e($c['body']) ?></p>
            </div>
        <?php endforeach; ?>
        <form method="post" action="/learning/<?= e($pid) ?>/comments" class="mt-2 flex gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="entity_type" value="program"><input type="hidden" name="entity_id" value="<?= e($pid) ?>">
            <input name="body" required placeholder="Ask a question…" class="grow rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Post</button>
        </form>
    </div>
</div>
