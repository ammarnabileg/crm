<?php /** Guest layout — auth screens, the installer and public pages. */ ?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? config('app.name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-brand-50">
    <?= $this->yield('content') ?>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
    <?= $this->yield('scripts') ?>
</body>
</html>
