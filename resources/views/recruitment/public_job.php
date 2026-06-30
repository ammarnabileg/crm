<?php
/** @var array<string,mixed> $job */
/** @var string $token */
/** @var bool $authenticated */
/** @var string|null $status */
/** @var string|null $error */
?>
<?php $deadlineTs = ! empty($job['deadline_at']) ? strtotime((string) $job['deadline_at'] . ' UTC') : null; ?>
<h1 class="mb-1 text-xl font-semibold text-slate-900"><?= e($job['title']) ?></h1>
<p class="mb-4 text-sm text-slate-500"><?= $job['location'] ? e($job['location']) . ' · ' : '' ?><?= e($job['employment_type'] ?: 'Full-time') ?></p>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

<?php if ($deadlineTs): ?>
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">
        Applications close in <span class="font-semibold" data-countdown="<?= e((string) max(0, $deadlineTs - time())) ?>">…</span>
        <span class="text-amber-500">· <?= e(gmdate('Y-m-d H:i', $deadlineTs)) ?> UTC</span>
    </div>
<?php endif; ?>

<div class="mb-6 whitespace-pre-line text-sm text-slate-600"><?= e($job['description'] ?: 'No description provided.') ?></div>

<?php if ($authenticated): ?>
    <?php if ($deadlineTs !== null && $deadlineTs <= time()): ?>
        <div class="rounded-lg bg-slate-100 px-4 py-3 text-center text-sm text-slate-500">Applications for this role are closed.</div>
    <?php else: ?>
        <form method="post" action="/jobs/public/<?= e($token) ?>/apply" class="space-y-3">
            <?= csrf_field() ?>
            <textarea name="cover_note" rows="3" placeholder="Add a short note (optional)…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"></textarea>
            <input name="available_from" type="text" maxlength="255" placeholder="When can you start? (e.g. Immediately, 2 weeks' notice)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Apply now</button>
        </form>
    <?php endif; ?>
<?php else: ?>
    <a href="/login" class="block w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-indigo-700">Sign in to apply</a>
    <p class="mt-3 text-center text-sm text-slate-500">No account? <a href="/register" class="font-medium text-indigo-600 hover:underline">Create one</a></p>
<?php endif; ?>

<?php if ($deadlineTs): ?>
<script>
document.querySelectorAll('[data-countdown]').forEach(function (el) {
    var s = parseInt(el.getAttribute('data-countdown'), 10) || 0;
    function fmt(t) {
        if (t <= 0) return 'closed';
        var d = Math.floor(t / 86400), h = Math.floor((t % 86400) / 3600), m = Math.floor((t % 3600) / 60), sec = t % 60;
        return (d > 0 ? d + 'd ' : '') + h + 'h ' + m + 'm ' + sec + 's';
    }
    (function tick() { el.textContent = fmt(s); if (s > 0) { s--; setTimeout(tick, 1000); } })();
});
</script>
<?php endif; ?>
