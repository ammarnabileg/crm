<?php
/** @var array<string,mixed> $avatar */
$a = $avatar;
$greeting = trim((string) ($a['greeting'] ?? '')) !== '' ? (string) $a['greeting'] : "Hi, I'm {$a['name']}. Thanks for joining — I'll ask you a few questions about the role. Take your time.";
?>
<div class="mb-6">
    <a href="/avatars" class="text-xs text-slate-400 hover:text-slate-600">← Avatars</a>
    <h1 class="mt-1 text-2xl font-semibold text-slate-900">Preview · <?= e($a['name']) ?></h1>
    <p class="mt-1 text-sm text-slate-500">A non-live preview of how this avatar opens an interview. Live video uses the workspace's HeyGen key.</p>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <!-- Avatar stage -->
    <div class="rounded-2xl border border-slate-200 bg-slate-900 p-6 text-center shadow-sm">
        <div class="mx-auto flex h-28 w-28 items-center justify-center overflow-hidden rounded-full bg-indigo-500 text-4xl font-bold text-white">
            <?php if (! empty($a['image_url'])): ?><img src="<?= e($a['image_url']) ?>" alt="" class="h-full w-full object-cover">
            <?php else: ?><?= e(strtoupper(substr((string) $a['name'], 0, 1))) ?><?php endif; ?>
        </div>
        <div class="mt-3 text-sm font-semibold text-white"><?= e($a['name']) ?></div>
        <div class="text-xs text-slate-400"><span class="capitalize"><?= e($a['persona']) ?></span> · <?= e($a['language']) ?><?= ! empty($a['voice']) ? ' · ' . e($a['voice']) : '' ?></div>
        <div class="mt-2 inline-block rounded-full px-2 py-0.5 text-xs font-medium <?= ($a['status'] ?? 'active') === 'active' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-600 text-slate-300' ?>"><?= e($a['status'] ?? 'active') ?></div>
    </div>

    <!-- Sample conversation -->
    <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Sample opening</h2>
        <div class="space-y-3">
            <div class="flex justify-start"><div class="max-w-[80%] rounded-2xl bg-slate-100 px-4 py-2 text-sm text-slate-800"><?= nl2br(e($greeting)) ?></div></div>
            <div class="flex justify-start"><div class="max-w-[80%] rounded-2xl bg-slate-100 px-4 py-2 text-sm text-slate-800">To start, could you tell me a bit about yourself and your background?</div></div>
            <div class="flex justify-end"><div class="max-w-[80%] rounded-2xl bg-indigo-600 px-4 py-2 text-sm text-white">(the candidate answers here…)</div></div>
        </div>
        <?php if (! empty($a['prompt'])): ?>
            <div class="mt-5 border-t border-slate-100 pt-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">System prompt</div>
                <p class="mt-1 text-sm text-slate-600"><?= nl2br(e((string) $a['prompt'])) ?></p>
            </div>
        <?php endif; ?>
        <?php if (! empty($a['knowledge'])): ?>
            <div class="mt-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Knowledge brief</div>
                <p class="mt-1 text-sm text-slate-600"><?= nl2br(e((string) $a['knowledge'])) ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>
