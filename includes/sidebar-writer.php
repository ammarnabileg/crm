<?php
$user = currentUser();
?>
<div class="w-64 bg-gray-900 text-white min-h-screen flex flex-col flex-shrink-0">
  <div class="p-4 border-b border-gray-700">
    <a href="/" class="text-lg font-bold text-[#F5C518]">مربح</a>
    <div class="text-sm text-gray-300 mt-1"><?= h($user['name']) ?></div>
    <div class="text-xs text-gray-500 mt-1">كود: <?= h($user['affiliate_code'] ?? '') ?></div>
    <?php
    try {
        $db = getDB();
        $bal = $db->prepare("SELECT SUM(amount) as total FROM commissions WHERE writer_id = ? AND status IN ('approved','payable') AND is_deleted = 0");
        $bal->execute([$user['id']]);
        $balance = $bal->fetch()['total'] ?? 0;
    } catch (Exception $e) { $balance = 0; }
    ?>
    <div class="mt-2 bg-[#F5C518] text-black rounded-lg p-2 text-center">
      <div class="text-xs">رصيد جاهز للسحب</div>
      <div class="font-bold"><?= formatMoney((float)$balance) ?></div>
    </div>
  </div>
  <nav class="flex-1 p-4 space-y-1">
    <?php
    $links = [
      ['/writer/', 'لوحة التحكم', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
      ['/writer/article-new.php', 'إضافة مقالة', 'M12 4v16m8-8H4'],
      ['/writer/articles.php', 'مقالاتي', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
      ['/writer/leads.php', 'متابعة العملاء', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
      ['/writer/wallet.php', 'المحفظة', 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
      ['/writer/complaints.php', 'الشكاوي', 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
      ['/logout.php', 'تسجيل الخروج', 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1'],
    ];
    $current = strtok($_SERVER['REQUEST_URI'], '?');
    foreach ($links as [$href, $label, $icon]):
        $isActive = ($current === $href) || ($href !== '/writer/' && strpos($current, $href) !== false);
    ?>
      <a href="<?= h($href) ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm <?= $isActive ? 'bg-[#F5C518] text-black font-medium' : 'text-gray-300 hover:bg-gray-800' ?>">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icon ?>"/></svg>
        <?= $label ?>
      </a>
    <?php endforeach; ?>
  </nav>
</div>
