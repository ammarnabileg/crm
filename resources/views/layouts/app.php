<?php
/** @var string $content */
/** @var array<string,mixed> $user */
/** @var list<array{label:string,route:string,permission:string}> $sidebar */
/** @var string|null $workspaceName */
?>
<!DOCTYPE html>
<html lang="<?= e(config('app.locale', 'en')) ?>" dir="<?= config('app.locale') === 'ar' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HaHireAI</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
<div class="flex min-h-screen">
    <!-- Single dynamic sidebar — generated from permissions, not roles -->
    <aside class="hidden w-64 shrink-0 flex-col border-e border-slate-200 bg-white md:flex">
        <div class="flex h-14 items-center gap-2 border-b border-slate-200 px-5">
            <span class="text-lg font-bold tracking-tight text-slate-900">HaHire<span class="text-indigo-600">AI</span></span>
        </div>
        <?php if ($workspaceName !== null): ?>
            <div class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400"><?= e($workspaceName) ?></div>
        <?php endif; ?>
        <nav class="flex-1 space-y-1 px-3 py-2">
            <?php foreach ($sidebar as $item): ?>
                <a href="<?= e($item['route']) ?>" class="flex items-center rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    <?= e($item['label']) ?>
                </a>
            <?php endforeach; ?>
            <?php if ($sidebar === []): ?>
                <p class="px-3 py-2 text-sm text-slate-400">No modules available yet.</p>
            <?php endif; ?>
        </nav>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex h-14 items-center justify-between border-b border-slate-200 bg-white px-6">
            <div class="text-sm text-slate-500"><?= $workspaceName !== null ? e($workspaceName) : 'HaHireAI' ?></div>
            <div class="flex items-center gap-4">
                <span class="text-sm font-medium text-slate-700"><?= e($user['name'] ?? '') ?></span>
                <form method="post" action="/logout">
                    <?= csrf_field() ?>
                    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">Sign out</button>
                </form>
            </div>
        </header>
        <main class="flex-1 p-6"><?= $content ?></main>
    </div>
</div>
</body>
</html>
