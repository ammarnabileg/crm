<?php
/** @var array<string, list<array<string,mixed>>> $board */
/** @var array<string,string> $statuses */
/** @var bool $canManage */
/** @var string|null $status */
?>
<div class="mb-6 flex items-end justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Pipeline</h1>
        <p class="mt-1 text-sm text-slate-500">Drag a candidate between stages, or select several and move them together. The AI recommends; you decide.</p>
    </div>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>

<?php if ($canManage): ?>
    <!-- Bulk action bar (cards associate via the form= attribute, so no nested forms) -->
    <form id="bulkForm" method="post" action="/pipeline/bulk-status" class="mb-4 flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm shadow-sm">
        <?= csrf_field() ?>
        <span data-bulk-count class="font-medium text-slate-600">0 selected</span>
        <span class="text-slate-300">·</span>
        <label class="text-slate-500">Move to
            <select name="status" class="ml-1 rounded border border-slate-300 px-2 py-1 text-xs">
                <?php foreach ($statuses as $sk => $sl): ?><option value="<?= e($sk) ?>"><?= e($sl) ?></option><?php endforeach; ?>
            </select>
        </label>
        <button data-bulk-submit disabled class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-40">Move selected</button>
    </form>
<?php endif; ?>

<div class="flex gap-4 overflow-x-auto pb-4" data-board data-csrf="<?= e(csrf_token()) ?>" data-can-manage="<?= $canManage ? '1' : '0' ?>">
    <?php foreach ($statuses as $key => $label): ?>
        <?php $cards = $board[$key] ?? []; ?>
        <div class="w-72 shrink-0">
            <div class="mb-2 flex items-center justify-between px-1">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e($label) ?></span>
                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600"><?= count($cards) ?></span>
            </div>
            <div class="space-y-2 rounded-2xl bg-slate-100/70 p-2 min-h-[6rem]" data-dropzone data-status="<?= e($key) ?>">
                <?php foreach ($cards as $c): ?>
                    <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm <?= $canManage ? 'cursor-grab' : '' ?>" <?= $canManage ? 'draggable="true"' : '' ?> data-card data-app-id="<?= e($c['id']) ?>" data-status="<?= e($key) ?>">
                        <div class="flex items-start gap-2">
                            <?php if ($canManage): ?>
                                <input type="checkbox" name="application_ids[]" value="<?= e($c['id']) ?>" form="bulkForm" data-bulk-cb class="mt-0.5 rounded border-slate-300">
                            <?php endif; ?>
                            <div class="min-w-0">
                                <a href="/candidates/<?= e($c['user_id']) ?>" class="text-sm font-medium text-indigo-600 hover:underline"><?= e($c['name']) ?></a>
                                <div class="truncate text-xs text-slate-400"><?= e($c['job_title']) ?></div>
                            </div>
                        </div>
                        <?php if ($canManage): ?>
                            <form method="post" action="/applications/<?= e($c['id']) ?>/status" class="mt-2" data-move-form>
                                <?= csrf_field() ?>
                                <input type="hidden" name="redirect_to" value="/pipeline">
                                <select name="status" onchange="this.form.submit()" class="w-full rounded border border-slate-300 px-2 py-1 text-xs">
                                    <?php foreach ($statuses as $sk => $sl): ?>
                                        <option value="<?= e($sk) ?>" <?= $sk === $key ? 'selected' : '' ?>><?= e($sl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($cards === []): ?><p class="px-2 py-3 text-center text-xs text-slate-400" data-empty>—</p><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($canManage): ?>
<script>
(function () {
    var board = document.querySelector('[data-board]');
    if (!board) return;
    var csrf = board.getAttribute('data-csrf');

    // Bulk selection counter + enable.
    var cbs = function () { return Array.prototype.slice.call(document.querySelectorAll('[data-bulk-cb]')); };
    var sync = function () {
        var n = cbs().filter(function (c) { return c.checked; }).length;
        var label = document.querySelector('[data-bulk-count]');
        var btn = document.querySelector('[data-bulk-submit]');
        if (label) label.textContent = n + ' selected';
        if (btn) btn.disabled = n === 0;
    };
    document.addEventListener('change', function (e) { if (e.target && e.target.matches('[data-bulk-cb]')) sync(); });

    // Drag & drop → POST a status change for the dropped card.
    var dragId = null;
    document.querySelectorAll('[data-card]').forEach(function (card) {
        card.addEventListener('dragstart', function (e) {
            dragId = card.getAttribute('data-app-id');
            e.dataTransfer.effectAllowed = 'move';
            card.classList.add('opacity-50');
        });
        card.addEventListener('dragend', function () { card.classList.remove('opacity-50'); });
    });
    document.querySelectorAll('[data-dropzone]').forEach(function (zone) {
        zone.addEventListener('dragover', function (e) { e.preventDefault(); zone.classList.add('ring-2', 'ring-indigo-300'); });
        zone.addEventListener('dragleave', function () { zone.classList.remove('ring-2', 'ring-indigo-300'); });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('ring-2', 'ring-indigo-300');
            if (!dragId) return;
            var target = zone.getAttribute('data-status');
            var f = document.createElement('form');
            f.method = 'post';
            f.action = '/applications/' + dragId + '/status';
            f.innerHTML = '<input type="hidden" name="_csrf" value="' + csrf + '">'
                + '<input type="hidden" name="status" value="' + target + '">'
                + '<input type="hidden" name="redirect_to" value="/pipeline">';
            document.body.appendChild(f);
            f.submit();
        });
    });
    sync();
})();
</script>
<?php endif; ?>
