<?php
/** @var string $content */
/** @var string $title */
?>
<!DOCTYPE html>
<html lang="<?= e(config('app.locale', 'en')) ?>" dir="<?= config('app.locale') === 'ar' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title><?= e($title ?? 'Careers') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <?php if (is_file(dirname(__DIR__, 3) . '/public/assets/tailwind.css')): ?>
        <link rel="stylesheet" href="/assets/tailwind.css">
    <?php else: ?>
        <script src="https://cdn.tailwindcss.com"></script>
    <?php endif; ?>
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased">
    <div class="flex min-h-screen flex-col">
        <main class="flex-1"><?= $content ?></main>
        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-center px-6 py-5">
                <a href="/" class="flex items-center gap-1.5 text-xs font-medium text-slate-400 hover:text-slate-600">
                    Powered by
                    <span class="flex items-center gap-1">
                        <span class="flex h-4 w-4 items-center justify-center rounded bg-indigo-600 text-[9px] font-bold text-white">Ha</span>
                        HaHire<span class="text-indigo-600">AI</span>
                    </span>
                </a>
            </div>
        </footer>
    </div>
</body>
</html>
