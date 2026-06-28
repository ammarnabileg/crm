<?php /** @var string $content */ /** @var string $title */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Document') ?> — HaHireAI</title>
    <?php if (is_file(dirname(__DIR__, 3) . '/public/assets/tailwind.css')): ?>
        <link rel="stylesheet" href="/assets/tailwind.css">
    <?php else: ?>
        <script src="https://cdn.tailwindcss.com"></script>
    <?php endif; ?>
    <style>@media print { .no-print { display: none !important; } body { background: #fff; } }</style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
    <div class="mx-auto my-8 max-w-3xl">
        <div class="no-print mb-4 flex justify-end gap-2">
            <button onclick="window.print()" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Print / Save as PDF</button>
            <a href="/offers" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back</a>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-10 shadow-sm"><?= $content ?></div>
    </div>
</body>
</html>
