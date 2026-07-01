<?php
/**
 * @var array<string,mixed> $program
 * @var list<array<string,mixed>> $structure   sections, each with ['items']
 * @var array<string,list<array<string,mixed>>> $quizzes  quiz item_id => questions(+options)
 * @var list<string> $tags
 * @var list<array<string,mixed>> $editors
 * @var list<array<string,mixed>> $todos
 * @var list<array<string,mixed>> $roster
 * @var array{total:int,completed:int,in_progress:int,not_started:int,avg_percent:int} $stats
 * @var list<array<string,mixed>> $assignments
 * @var list<array<string,mixed>> $prerequisites
 * @var list<array<string,mixed>> $otherPrograms
 * @var list<array<string,mixed>> $activity
 * @var list<array<string,mixed>> $comments
 * @var list<array<string,mixed>> $members
 * @var array<string,array{label:string,icon:string,hint:string}> $itemTypes
 * @var array<string,string> $difficulties
 * @var array<string,string> $completionRules
 * @var array<string,string> $todoModes
 * @var array<string,string> $priorities
 * @var bool $canManage
 * @var bool $canPublish
 * @var bool $canAssign
 * @var string $currentUserId
 * @var string|null $status
 */
$pid = (string) $program['id'];
$badge = ['draft' => 'bg-slate-100 text-slate-600', 'published' => 'bg-emerald-100 text-emerald-700', 'archived' => 'bg-amber-100 text-amber-700'];
$st = (string) $program['status'];
?>
<div class="mb-4">
    <a href="/learning" class="text-sm text-slate-500 hover:text-slate-700">← All programs</a>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<!-- Header -->
<div class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $badge[$st] ?? 'bg-slate-100' ?>"><?= e(ucfirst($st)) ?></span>
                <span class="text-xs text-slate-400">v<?= (int) $program['version'] ?> · <?= e($difficulties[(string) $program['difficulty']] ?? $program['difficulty']) ?></span>
            </div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900"><?= e($program['title']) ?></h1>
            <?php if (! empty($program['summary'])): ?><p class="mt-1 text-sm text-slate-500"><?= e($program['summary']) ?></p><?php endif; ?>
            <?php if ($tags !== []): ?>
                <div class="mt-2 flex flex-wrap gap-1">
                    <?php foreach ($tags as $t): ?><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500"><?= e($t) ?></span><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($canPublish): ?>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <?php if ($st !== 'published'): ?>
                    <form method="post" action="/learning/<?= e($pid) ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="published"><button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700">Publish</button></form>
                <?php else: ?>
                    <form method="post" action="/learning/<?= e($pid) ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="draft"><button class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Unpublish</button></form>
                <?php endif; ?>
                <?php if ($st !== 'archived'): ?>
                    <form method="post" action="/learning/<?= e($pid) ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="archived"><button class="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-sm font-medium text-amber-700 hover:bg-amber-50">Archive</button></form>
                <?php endif; ?>
                <form method="post" action="/learning/<?= e($pid) ?>/snapshot"><?= csrf_field() ?><button class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Snapshot v<?= (int) $program['version'] ?></button></form>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <!-- LEFT: structure -->
    <div class="space-y-4 lg:col-span-2">
        <?php if (! empty($program['description'])): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-slate-900">About this program</h2>
                <div class="prose-sm whitespace-pre-line text-sm text-slate-600"><?= e($program['description']) ?></div>
            </div>
        <?php endif; ?>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Curriculum</h2>
            <?php if ($structure === []): ?>
                <p class="text-sm text-slate-400">No sections yet. Add one below to start building the curriculum.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($structure as $si => $section): ?>
                        <div class="rounded-xl border border-slate-100">
                            <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-2.5">
                                <div class="font-medium text-slate-800">
                                    <span class="text-slate-400"><?= $si + 1 ?>.</span> <?= e($section['title']) ?>
                                    <?php if ((int) $section['is_required'] === 1): ?><span class="ml-1 text-[11px] text-rose-500">required</span><?php endif; ?>
                                </div>
                                <?php if ($canManage): ?>
                                    <div class="flex items-center gap-3">
                                        <details class="relative">
                                            <summary class="cursor-pointer text-xs text-slate-500 hover:text-slate-700">Edit</summary>
                                            <form method="post" action="/learning/<?= e($pid) ?>/sections/<?= e($section['id']) ?>" class="absolute right-0 z-10 mt-1 w-64 space-y-1 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                                                <?= csrf_field() ?>
                                                <input name="title" value="<?= e($section['title']) ?>" class="w-full rounded border border-slate-300 px-2 py-1 text-sm">
                                                <input name="description" value="<?= e($section['description'] ?? '') ?>" placeholder="Description" class="w-full rounded border border-slate-300 px-2 py-1 text-xs">
                                                <label class="block text-xs text-slate-500"><input type="checkbox" name="is_required" value="1" <?= (int) $section['is_required'] === 1 ? 'checked' : '' ?>> Required</label>
                                                <button class="rounded bg-slate-800 px-2 py-1 text-xs font-semibold text-white">Save</button>
                                            </form>
                                        </details>
                                        <form method="post" action="/learning/<?= e($pid) ?>/sections/<?= e($section['id']) ?>/delete" onsubmit="return confirm('Delete this section and its items?')"><?= csrf_field() ?><button class="text-xs text-rose-500 hover:text-rose-700">Delete</button></form>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="divide-y divide-slate-50">
                                <?php foreach ((array) $section['items'] as $item): ?>
                                    <?php $type = (string) $item['type']; $meta = $itemTypes[$type] ?? ['label' => $type, 'icon' => 'square']; ?>
                                    <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                                        <div class="min-w-0">
                                            <span class="text-xs font-medium uppercase tracking-wide text-indigo-500"><?= e($meta['label']) ?></span>
                                            <span class="ml-1 text-sm text-slate-700"><?= e($item['title']) ?></span>
                                            <?php if (! empty($item['url'])): ?><a href="<?= e($item['url']) ?>" target="_blank" rel="noopener" class="ml-1 text-xs text-indigo-600 hover:underline">open ↗</a><?php endif; ?>
                                            <?php if ((int) $item['is_required'] === 0): ?><span class="ml-1 text-[11px] text-slate-400">optional</span><?php endif; ?>
                                        </div>
                                        <?php if ($canManage): ?>
                                            <div class="flex items-center gap-2">
                                                <details class="relative">
                                                    <summary class="cursor-pointer text-xs text-slate-400 hover:text-slate-600">edit</summary>
                                                    <form method="post" action="/learning/<?= e($pid) ?>/items/<?= e($item['id']) ?>" class="absolute right-0 z-10 mt-1 w-64 space-y-1 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                                                        <?= csrf_field() ?>
                                                        <input name="title" value="<?= e($item['title']) ?>" class="w-full rounded border border-slate-300 px-2 py-1 text-sm">
                                                        <?php if (! empty($item['url']) || in_array($type, ['link', 'video'], true)): ?><input name="url" value="<?= e($item['url'] ?? '') ?>" placeholder="URL" class="w-full rounded border border-slate-300 px-2 py-1 text-xs"><?php endif; ?>
                                                        <textarea name="body" rows="2" placeholder="Content" class="w-full rounded border border-slate-300 px-2 py-1 text-xs"><?= e($item['body'] ?? '') ?></textarea>
                                                        <label class="block text-xs text-slate-500"><input type="checkbox" name="is_required" value="1" <?= (int) $item['is_required'] === 1 ? 'checked' : '' ?>> Required</label>
                                                        <button class="rounded bg-slate-800 px-2 py-1 text-xs font-semibold text-white">Save</button>
                                                    </form>
                                                </details>
                                                <form method="post" action="/learning/<?= e($pid) ?>/items/<?= e($item['id']) ?>/delete"><?= csrf_field() ?><button class="text-xs text-slate-400 hover:text-rose-600">✕</button></form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($type === 'quiz'): ?>
                                        <?php $qs = $quizzes[(string) $item['id']] ?? []; ?>
                                        <div class="border-t border-slate-50 bg-slate-50/50 px-4 py-2">
                                            <?php foreach ($qs as $qi => $q): ?>
                                                <div class="mb-1 flex items-start justify-between gap-2">
                                                    <div class="text-xs text-slate-600">
                                                        <span class="font-medium"><?= $qi + 1 ?>.</span> <?= e($q['question']) ?>
                                                        <span class="text-slate-400">(<?= e(implode(' / ', array_map(static fn ($o) => $o['label'] . ((int) $o['is_correct'] === 1 ? ' ✓' : ''), (array) $q['options']))) ?>)</span>
                                                    </div>
                                                    <?php if ($canManage): ?><form method="post" action="/learning/<?= e($pid) ?>/questions/<?= e($q['id']) ?>/delete"><?= csrf_field() ?><button class="text-[11px] text-slate-400 hover:text-rose-600">✕</button></form><?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if ($canManage): ?>
                                                <details class="mt-1"><summary class="cursor-pointer text-[11px] font-medium text-indigo-600">+ Add question</summary>
                                                    <form method="post" action="/learning/<?= e($pid) ?>/items/<?= e($item['id']) ?>/questions" class="mt-1 space-y-1">
                                                        <?= csrf_field() ?>
                                                        <input name="question" required placeholder="Question text" class="w-full rounded-lg border border-slate-300 px-2 py-1 text-xs">
                                                        <?php for ($oi = 0; $oi < 4; $oi++): ?>
                                                            <div class="flex items-center gap-1">
                                                                <input type="checkbox" name="correct[]" value="<?= $oi ?>" title="Correct">
                                                                <input name="option_label[]" placeholder="Option <?= $oi + 1 ?><?= $oi >= 2 ? ' (optional)' : '' ?>" class="grow rounded-lg border border-slate-300 px-2 py-1 text-xs">
                                                            </div>
                                                        <?php endfor; ?>
                                                        <button class="rounded-lg bg-indigo-600 px-2 py-1 text-[11px] font-semibold text-white">Add question</button>
                                                    </form>
                                                </details>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if ((array) $section['items'] === []): ?><p class="px-4 py-3 text-xs text-slate-400">No content in this section yet.</p><?php endif; ?>
                            </div>

                            <?php if ($canManage): ?>
                                <details class="border-t border-slate-100 px-4 py-2">
                                    <summary class="cursor-pointer text-xs font-medium text-indigo-600">+ Add content</summary>
                                    <form method="post" action="/learning/<?= e($pid) ?>/sections/<?= e($section['id']) ?>/items" class="mt-2 space-y-2">
                                        <?= csrf_field() ?>
                                        <div class="flex gap-2">
                                            <select name="type" class="w-1/3 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                                <?php foreach ($itemTypes as $tv => $tm): ?><option value="<?= $tv ?>"><?= e($tm['label']) ?></option><?php endforeach; ?>
                                            </select>
                                            <input name="title" required placeholder="Title" class="grow rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        </div>
                                        <input name="url" placeholder="URL (for link/video)" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        <textarea name="body" rows="2" placeholder="Content / notes (for lesson, note)" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"></textarea>
                                        <div class="flex items-center justify-between">
                                            <label class="text-xs text-slate-500"><input type="checkbox" name="is_required" value="1" checked> Required</label>
                                            <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">Add</button>
                                        </div>
                                    </form>
                                </details>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($canManage): ?>
                <form method="post" action="/learning/<?= e($pid) ?>/sections" class="mt-4 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-4">
                    <?= csrf_field() ?>
                    <div class="grow">
                        <label class="mb-1 block text-xs font-medium text-slate-600">New section</label>
                        <input name="title" required placeholder="Section title" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <label class="pb-2 text-xs text-slate-500"><input type="checkbox" name="is_required" value="1" checked> Required</label>
                    <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Add section</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- To-dos -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">To-dos</h2>
            <?php if ($todos === []): ?>
                <p class="text-sm text-slate-400">No to-dos yet.</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($todos as $todo): ?>
                        <?php $done = (string) $todo['status'] === 'done'; ?>
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 px-3 py-2 <?= $done ? 'opacity-60' : '' ?>">
                            <div class="min-w-0">
                                <span class="text-sm text-slate-700 <?= $done ? 'line-through' : '' ?>"><?= e($todo['title']) ?></span>
                                <span class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-500"><?= e($todo['completion_mode'] === 'manager' ? 'manager' : 'self') ?></span>
                                <div class="text-xs text-slate-400">
                                    <?= ! empty($todo['assignee_name']) ? e($todo['assignee_name']) : 'Unassigned' ?>
                                    <?php if (! empty($todo['due_date'])): ?> · due <?= e(substr((string) $todo['due_date'], 0, 10)) ?><?php endif; ?>
                                    · <?= e($todo['priority']) ?>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <?php if (! $done): ?>
                                    <form method="post" action="/learning/<?= e($pid) ?>/todos/<?= e($todo['id']) ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="done"><button class="text-xs font-medium text-emerald-600 hover:text-emerald-700">Complete</button></form>
                                <?php else: ?>
                                    <form method="post" action="/learning/<?= e($pid) ?>/todos/<?= e($todo['id']) ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="open"><button class="text-xs text-slate-500 hover:text-slate-700">Reopen</button></form>
                                <?php endif; ?>
                                <?php if ($canManage): ?><form method="post" action="/learning/<?= e($pid) ?>/todos/<?= e($todo['id']) ?>/delete"><?= csrf_field() ?><button class="text-xs text-slate-400 hover:text-rose-600">✕</button></form><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <form method="post" action="/learning/<?= e($pid) ?>/todos" class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                    <?= csrf_field() ?>
                    <input name="title" required placeholder="To-do title" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <div class="flex flex-wrap gap-2">
                        <select name="completion_mode" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            <?php foreach ($todoModes as $v => $l): ?><option value="<?= $v ?>"><?= e($l) ?></option><?php endforeach; ?>
                        </select>
                        <select name="priority" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            <?php foreach ($priorities as $v => $l): ?><option value="<?= $v ?>" <?= $v === 'normal' ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select>
                        <select name="assignee_user_id" class="grow rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            <option value="">Unassigned</option>
                            <?php foreach ($members as $m): ?><option value="<?= e($m['user_id']) ?>"><?= e($m['name']) ?></option><?php endforeach; ?>
                        </select>
                        <input name="due_date" type="date" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                        <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700">Add to-do</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <!-- Discussion -->
        <div id="discussion" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Discussion</h2>
            <?php foreach ($comments as $c): ?>
                <?php $deleted = ! empty($c['deleted_at']); ?>
                <div class="mb-3 rounded-xl border border-slate-100 p-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-700"><?= e($c['author_name'] ?? 'Someone') ?></span>
                        <span class="text-xs text-slate-400"><?= e(substr((string) $c['created_at'], 0, 16)) ?><?= ! empty($c['edited_at']) ? ' · edited' : '' ?></span>
                    </div>
                    <p class="mt-1 whitespace-pre-line text-sm text-slate-600 <?= $deleted ? 'italic text-slate-400' : '' ?>"><?= e($c['body']) ?></p>
                    <?php if (! empty($c['mentions'])): ?><p class="mt-1 text-xs text-indigo-500">@ <?= e(implode(', ', $c['mentions'])) ?></p><?php endif; ?>
                    <?php if (! $deleted && ((string) $c['author_user_id'] === $currentUserId || $canManage)): ?>
                        <div class="mt-1 flex items-center gap-3">
                            <?php if ((string) $c['author_user_id'] === $currentUserId): ?>
                                <details><summary class="cursor-pointer text-[11px] text-slate-400 hover:text-slate-600">edit</summary>
                                    <form method="post" action="/learning/<?= e($pid) ?>/comments/<?= e($c['id']) ?>" class="mt-1 flex gap-1"><?= csrf_field() ?>
                                        <input name="body" value="<?= e($c['body']) ?>" class="grow rounded border border-slate-300 px-2 py-1 text-xs">
                                        <button class="rounded bg-slate-700 px-2 py-1 text-[11px] text-white">Save</button>
                                    </form>
                                </details>
                            <?php endif; ?>
                            <form method="post" action="/learning/<?= e($pid) ?>/comments/<?= e($c['id']) ?>/delete"><?= csrf_field() ?><button class="text-[11px] text-slate-400 hover:text-rose-600">delete</button></form>
                        </div>
                    <?php endif; ?>
                    <?php foreach ((array) ($c['replies'] ?? []) as $reply): ?>
                        <div class="mt-2 ml-4 rounded-lg bg-slate-50 p-2">
                            <span class="text-xs font-medium text-slate-600"><?= e($reply['author_name'] ?? 'Someone') ?></span>
                            <p class="whitespace-pre-line text-xs text-slate-600"><?= e($reply['body']) ?></p>
                        </div>
                    <?php endforeach; ?>
                    <details class="mt-1"><summary class="cursor-pointer text-[11px] text-indigo-500">reply</summary>
                        <form method="post" action="/learning/<?= e($pid) ?>/comments" class="mt-1 flex gap-2"><?= csrf_field() ?>
                            <input type="hidden" name="entity_type" value="program"><input type="hidden" name="entity_id" value="<?= e($pid) ?>"><input type="hidden" name="parent_id" value="<?= e($c['id']) ?>">
                            <input name="body" required placeholder="Reply…" class="grow rounded-lg border border-slate-300 px-2 py-1 text-sm">
                            <button class="rounded-lg bg-slate-700 px-3 py-1 text-xs text-white">Send</button>
                        </form>
                    </details>
                </div>
            <?php endforeach; ?>
            <form method="post" action="/learning/<?= e($pid) ?>/comments" class="mt-2 flex gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="entity_type" value="program"><input type="hidden" name="entity_id" value="<?= e($pid) ?>">
                <input name="body" required placeholder="Write a comment… use @name to mention" class="grow rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Comment</button>
            </form>
        </div>
    </div>

    <!-- RIGHT: settings, assignment, roster, activity -->
    <div class="space-y-4">
        <?php if ($canAssign): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-1 text-sm font-semibold text-slate-900">Assign &amp; enroll</h2>
                <p class="mb-3 text-xs text-slate-400">Assign to a member (role/department/team supported too).</p>
                <form method="post" action="/learning/<?= e($pid) ?>/assign" class="space-y-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="assignee_type" value="user">
                    <select name="assignee_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Choose a member…</option>
                        <?php foreach ($members as $m): ?><option value="<?= e($m['user_id']) ?>"><?= e($m['name']) ?></option><?php endforeach; ?>
                    </select>
                    <input name="due_date" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Assign</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Roster + stats -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Learners</h2>
            <div class="mb-3 grid grid-cols-2 gap-2 text-center">
                <div class="rounded-lg bg-slate-50 p-2"><div class="text-lg font-semibold text-slate-800"><?= $stats['total'] ?></div><div class="text-[11px] text-slate-500">Enrolled</div></div>
                <div class="rounded-lg bg-emerald-50 p-2"><div class="text-lg font-semibold text-emerald-700"><?= $stats['completed'] ?></div><div class="text-[11px] text-emerald-600">Completed</div></div>
                <div class="rounded-lg bg-indigo-50 p-2"><div class="text-lg font-semibold text-indigo-700"><?= $stats['in_progress'] ?></div><div class="text-[11px] text-indigo-600">In progress</div></div>
                <div class="rounded-lg bg-slate-50 p-2"><div class="text-lg font-semibold text-slate-800"><?= $stats['avg_percent'] ?>%</div><div class="text-[11px] text-slate-500">Avg progress</div></div>
            </div>
            <?php if ($roster === []): ?>
                <p class="text-xs text-slate-400">No one enrolled yet.</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($roster as $e): ?>
                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-600"><?= e($e['user_name'] ?? '—') ?></span>
                                <span class="font-medium text-slate-700"><?= (int) $e['progress_percent'] ?>%</span>
                            </div>
                            <div class="mt-1 h-1.5 w-full rounded-full bg-slate-100"><div class="h-1.5 rounded-full <?= (string) $e['status'] === 'completed' ? 'bg-emerald-500' : 'bg-indigo-500' ?>" style="width:<?= max(2, (int) $e['progress_percent']) ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($canManage): ?>
            <!-- Settings -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Settings</h2>
                <form method="post" action="/learning/<?= e($pid) ?>" class="space-y-2">
                    <?= csrf_field() ?>
                    <input name="title" value="<?= e($program['title']) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <textarea name="summary" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><?= e($program['summary'] ?? '') ?></textarea>
                    <textarea name="description" rows="3" placeholder="Full description" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><?= e($program['description'] ?? '') ?></textarea>
                    <input name="category" value="<?= e($program['category'] ?? '') ?>" placeholder="Category" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="tags" value="<?= e(implode(', ', $tags)) ?>" placeholder="Tags" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <div class="flex gap-2">
                        <select name="difficulty" class="w-1/2 rounded-lg border border-slate-300 px-2 py-2 text-sm">
                            <?php foreach ($difficulties as $v => $l): ?><option value="<?= $v ?>" <?= (string) $program['difficulty'] === $v ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select>
                        <input name="estimated_minutes" type="number" min="0" value="<?= (int) $program['estimated_minutes'] ?>" class="w-1/2 rounded-lg border border-slate-300 px-2 py-2 text-sm">
                    </div>
                    <select name="completion_rule" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm">
                        <?php foreach ($completionRules as $v => $l): ?><option value="<?= $v ?>" <?= (string) $program['completion_rule'] === $v ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select>
                    <button class="w-full rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Save settings</button>
                </form>
                <form method="post" action="/learning/<?= e($pid) ?>/delete" onsubmit="return confirm('Delete this program?')" class="mt-2"><?= csrf_field() ?><button class="text-xs text-rose-500 hover:text-rose-700">Delete program</button></form>
            </div>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <!-- Prerequisites -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-1 text-sm font-semibold text-slate-900">Prerequisites</h2>
                <p class="mb-3 text-xs text-slate-400">Programs a learner should finish first.</p>
                <?php if (! empty($prerequisites)): ?>
                    <div class="mb-3 space-y-1">
                        <?php foreach ($prerequisites as $pre): ?>
                            <div class="flex items-center justify-between rounded-lg bg-slate-50 px-2 py-1 text-xs">
                                <span class="text-slate-600"><?= e($pre['title']) ?></span>
                                <form method="post" action="/learning/<?= e($pid) ?>/prerequisites/<?= e($pre['program_id']) ?>/delete"><?= csrf_field() ?><button class="text-slate-400 hover:text-rose-600">✕</button></form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="post" action="/learning/<?= e($pid) ?>/prerequisites" class="flex gap-2">
                    <?= csrf_field() ?>
                    <select name="prerequisite_program_id" required class="grow rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                        <option value="">Choose a program…</option>
                        <?php foreach ($otherPrograms as $op): ?><option value="<?= e($op['id']) ?>"><?= e($op['title']) ?></option><?php endforeach; ?>
                    </select>
                    <button class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Add</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Activity -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Activity</h2>
            <?php if ($activity === []): ?><p class="text-xs text-slate-400">No activity yet.</p><?php else: ?>
                <ul class="space-y-1.5">
                    <?php foreach (array_slice($activity, 0, 12) as $a): ?>
                        <li class="text-xs text-slate-500"><span class="text-slate-700"><?= e($a['actor_name'] ?? 'Someone') ?></span> <?= e($a['action']) ?> <?= e((string) ($a['entity_type'] ?? '')) ?><?php if (! empty($a['summary'])): ?> — <?= e($a['summary']) ?><?php endif; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
