<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin','account_manager');
$user = currentUser();

try {
    $db = getDB();
    $todayLeads = $db->query("SELECT COUNT(*) FROM leads WHERE DATE(created_at)=CURDATE() AND is_deleted=0")->fetchColumn();
    $pendingArticles = $db->query("SELECT COUNT(*) FROM articles WHERE status='pending' AND is_deleted=0")->fetchColumn();
    $totalWriters = $db->query("SELECT COUNT(*) FROM users WHERE role='writer' AND is_active=1 AND is_deleted=0")->fetchColumn();
    $totalBrokerCos = $db->query("SELECT COUNT(*) FROM broker_companies WHERE is_active=1 AND is_deleted=0")->fetchColumn();
    $pendingComms = $db->query("SELECT COUNT(*) FROM commissions WHERE status='pending' AND is_deleted=0")->fetchColumn();
    $totalLeads = $db->query("SELECT COUNT(*) FROM leads WHERE is_deleted=0")->fetchColumn();
    $recentLeads = $db->query("SELECT l.*, a.title as article_title, u.name as writer_name, bc.name as broker_name FROM leads l LEFT JOIN articles a ON a.id=l.article_id LEFT JOIN users u ON u.id=l.writer_id LEFT JOIN broker_companies bc ON bc.id=l.broker_company_id WHERE l.is_deleted=0 ORDER BY l.created_at DESC LIMIT 8")->fetchAll();
    $recentArticles = $db->query("SELECT a.*, u.name as author_name FROM articles a LEFT JOIN users u ON u.id=a.author_id WHERE a.status='pending' AND a.is_deleted=0 ORDER BY a.created_at DESC LIMIT 5")->fetchAll();
} catch (Exception $e) { $todayLeads=$pendingArticles=$totalWriters=$totalBrokerCos=$pendingComms=$totalLeads=0; $recentLeads=$recentArticles=[]; }
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>لوحة الإدارة - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">لوحة الإدارة</h1>
    <p class="text-gray-500 text-sm mt-1">نظرة شاملة على منصة مربح</p>
  </div>

  <!-- Stats -->
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
    <div class="bg-[#F5C518] rounded-2xl p-4 text-center">
      <div class="text-3xl font-black text-gray-900"><?= $todayLeads ?></div>
      <div class="text-xs text-gray-800 mt-1">عملاء اليوم</div>
    </div>
    <div class="bg-white rounded-2xl p-4 text-center shadow-sm border border-gray-100">
      <div class="text-3xl font-black text-yellow-500"><?= $pendingArticles ?></div>
      <div class="text-xs text-gray-500 mt-1">مقالات للمراجعة</div>
    </div>
    <div class="bg-white rounded-2xl p-4 text-center shadow-sm border border-gray-100">
      <div class="text-3xl font-black text-blue-500"><?= $totalWriters ?></div>
      <div class="text-xs text-gray-500 mt-1">كتاب نشطون</div>
    </div>
    <div class="bg-white rounded-2xl p-4 text-center shadow-sm border border-gray-100">
      <div class="text-3xl font-black text-purple-500"><?= $totalBrokerCos ?></div>
      <div class="text-xs text-gray-500 mt-1">شركات وسيطة</div>
    </div>
    <div class="bg-white rounded-2xl p-4 text-center shadow-sm border border-gray-100">
      <div class="text-3xl font-black text-orange-500"><?= $pendingComms ?></div>
      <div class="text-xs text-gray-500 mt-1">عمولات معلقة</div>
    </div>
    <div class="bg-white rounded-2xl p-4 text-center shadow-sm border border-gray-100">
      <div class="text-3xl font-black text-gray-700"><?= $totalLeads ?></div>
      <div class="text-xs text-gray-500 mt-1">إجمالي العملاء</div>
    </div>
  </div>

  <div class="grid md:grid-cols-2 gap-6">
    <!-- Recent Leads -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
      <div class="flex items-center justify-between p-4 border-b border-gray-50">
        <h2 class="font-bold text-gray-900">آخر العملاء</h2>
        <a href="/admin/leads.php" class="text-xs text-[#F5C518] font-medium">عرض الكل ←</a>
      </div>
      <div class="divide-y divide-gray-50">
        <?php foreach ($recentLeads as $lead): ?>
        <div class="p-3 flex items-center justify-between gap-2">
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900"><?= h($lead['name']) ?> <span class="text-gray-400 font-mono text-xs"><?= h($lead['phone']) ?></span></p>
            <p class="text-xs text-gray-400 mt-0.5"><?= h($lead['article_title'] ?? 'مباشر') ?> <?= $lead['writer_name'] ? '• ' . h($lead['writer_name']) : '' ?></p>
          </div>
          <div class="flex flex-col items-end gap-1">
            <?= statusBadge($lead['status']) ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($recentLeads)): ?><p class="text-center text-gray-400 py-6 text-sm">لا توجد عملاء</p><?php endif; ?>
      </div>
    </div>

    <!-- Pending Articles -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
      <div class="flex items-center justify-between p-4 border-b border-gray-50">
        <h2 class="font-bold text-gray-900">مقالات قيد المراجعة</h2>
        <a href="/admin/articles.php?status=pending" class="text-xs text-[#F5C518] font-medium">عرض الكل ←</a>
      </div>
      <div class="divide-y divide-gray-50">
        <?php foreach ($recentArticles as $art): ?>
        <div class="p-3 flex items-center justify-between gap-2">
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate"><?= h($art['title']) ?></p>
            <p class="text-xs text-gray-400 mt-0.5"><?= h($art['author_name']) ?> • <?= timeAgo($art['created_at']) ?></p>
          </div>
          <a href="/admin/article-review.php?id=<?= $art['id'] ?>" class="text-xs bg-[#F5C518] text-black px-3 py-1 rounded-lg font-medium flex-shrink-0">مراجعة</a>
        </div>
        <?php endforeach; ?>
        <?php if (empty($recentArticles)): ?><p class="text-center text-gray-400 py-6 text-sm">لا توجد مقالات للمراجعة</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>
</div>
</body></html>
