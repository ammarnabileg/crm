<?php
$user = currentUser();
?>
<div class="w-64 bg-gray-900 text-white min-h-screen flex flex-col flex-shrink-0">
  <div class="p-4 border-b border-gray-700">
    <a href="/" class="text-lg font-bold text-[#F5C518]">مربح</a>
    <div class="text-sm text-gray-300 mt-1"><?= h($user['name']) ?></div>
    <div class="text-xs text-[#F5C518] mt-1">
      <?php
      $roleLabels = ['super_admin' => 'مدير عام', 'admin' => 'مدير', 'account_manager' => 'مدير حسابات'];
      echo $roleLabels[$user['role']] ?? $user['role'];
      ?>
    </div>
  </div>
  <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
    <?php
    $links = [
      ['/admin/', 'لوحة التحكم', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
      ['/admin/leads.php', 'إدارة العملاء', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
      ['/admin/articles.php', 'إدارة المقالات', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
      ['/admin/projects.php', 'المشاريع', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
      ['/admin/cities.php', 'المدن', 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z'],
      ['/admin/companies.php', 'الشركات', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
      ['/admin/users.php', 'المستخدمون', 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
      ['/admin/commissions.php', 'العمولات', 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
      ['/admin/complaints.php', 'الشكاوي', 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
      ['/admin/settings.php', 'الإعدادات', 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
      ['/logout.php', 'تسجيل الخروج', 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1'],
    ];
    $current = strtok($_SERVER['REQUEST_URI'], '?');
    foreach ($links as [$href, $label, $icon]):
        $isActive = ($current === $href) || ($href !== '/admin/' && strpos($current, $href) !== false);
    ?>
      <a href="<?= h($href) ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm <?= $isActive ? 'bg-[#F5C518] text-black font-medium' : 'text-gray-300 hover:bg-gray-800' ?>">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icon ?>"/></svg>
        <?= $label ?>
      </a>
    <?php endforeach; ?>
  </nav>
</div>
