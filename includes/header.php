<?php
require_once __DIR__ . '/../includes/functions.php';
$siteName = getSetting('site_name', 'مربح');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? h($pageTitle) . ' - ' . h($siteName) : h($siteName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { cairo: ['Cairo', 'sans-serif'] },
          colors: { brand: '#F5C518' }
        }
      }
    }
  </script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>
    body { font-family: 'Cairo', sans-serif; }
    .prose { direction: rtl; text-align: right; }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">
