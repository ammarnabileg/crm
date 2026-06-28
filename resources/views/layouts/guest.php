<?php /** @var string $content */ /** @var string $title */ ?>
<!DOCTYPE html>
<html lang="<?= e(config('app.locale', 'en')) ?>" dir="<?= config('app.locale') === 'ar' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'HaHireAI') ?> — HaHireAI</title>
    <?php if (is_file(dirname(__DIR__, 3) . '/public/assets/tailwind.css')): ?>
        <link rel="stylesheet" href="/assets/tailwind.css">
    <?php else: ?>
        <script src="https://cdn.tailwindcss.com"></script>
    <?php endif; ?>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="mb-6 text-center">
                <div class="text-2xl font-bold tracking-tight text-slate-900">HaHire<span class="text-indigo-600">AI</span></div>
                <p class="mt-1 text-sm text-slate-500">AI-native hiring operations</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                <?= $content ?>
            </div>
        </div>
    </div>
</body>
</html>
