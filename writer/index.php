<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/', 'writer');
$user = currentUser();

try {
    $db = getDB();
    $totalArticles = $db->prepare("SELECT COUNT(*) FROM articles WHERE author_id=? AND is_deleted=0"); $totalArticles->execute([$user['id']]); $totalArticles = $totalArticles->fetchColumn();
    $pendingArticles = $db->prepare("SELECT COUNT(*) FROM articles WHERE author_id=? AND status='pending' AND is_deleted=0"); $pendingArticles->execute([$user['id']]); $pendingArticles = $pendingArticles->fetchColumn();
    $totalLeads = $db->prepare("SELECT COUNT(*) FROM leads WHERE writer_id=? AND is_deleted=0"); $totalLeads->execute([$user['id']]); $totalLeads = $totalLeads->fetchColumn();
    $totalComm = $db->prepare("SELECT SUM(amount) FROM commissions WHERE writer_id=? AND status IN ('approved','payable','paid') AND is_deleted=0"); $totalComm->execute([$user['id']]); $totalComm = $totalComm->fetchColumn() ?? 0;
    $recentArticles = $db->prepare("SELECT a.*, c.name_ar as city_name FROM articles a LEFT JOIN cities c ON c.id=a.city_id WHERE a.author_id=? AND a.is_deleted=0 ORDER BY a.created_at DESC LIMIT 5"); $recentArticles->execute([$user['id']]); $recentArticles = $recentArticles->fetchAll();
    $recentLeads = $db->prepare("SELECT l.*, a.title as article_title FROM leads l LEFT JOIN articles a ON a.id=l.article_id WHERE l.writer_id=? AND l.is_deleted=0 ORDER BY l.created_at DESC LIMIT 5"); $recentLeads->execute([$user['id']]); $recentLeads = $recentLeads->fetchAll();
} catch (Exception $e) { $totalArticles=$pendingArticles=$totalLeads=$totalComm=0; $recentArticles=$recentLeads=[]; }
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>لوحة الكاتب - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-writer.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">أهلاً، <?= h($user['name']) ?>!</h1>
    <p class="text-gray-500 text-sm mt-1">لوحة تحكم الكاتب</p>
  </div>

  <!-- Stats -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
      <div class="text-3xl font-black text-gray-900"><?= $totalArticles ?></div>
      <div class="text-sm text-gray-500 mt-1">إجمالي المقالات</div>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
      <div class="text-3xl font-black text-yellow-500"><?= $pendingArticles ?></div>
      <div class="text-sm text-gray-500 mt-1">قيد المراجعة</div>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
      <div class="text-3xl font-black text-blue-500"><?= $totalLeads ?></div>
      <div class="text-sm text-gray-500 mt-1">إجمالي العملاء</div>
    </div>
    <div class="bg-[#F5C518] rounded-2xl p-5 shadow-sm">
      <div class="text-3xl font-black text-gray-900"><?= formatMoney((float)$totalComm) ?></div>
      <div class="text-sm text-gray-800 mt-1">عمولات مكتسبة</div>
    </div>
  </div>

  <div class="grid md:grid-cols-2 gap-6">
    <!-- Recent Articles -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
      <div class="flex items-center justify-between p-4 border-b border-gray-50">
        <h2 class="font-bold text-gray-900">آخر المقالات</h2>
        <a href="/writer/article-new.php" class="text-xs bg-[#F5C518] text-black px-3 py-1.5 rounded-lg font-medium">+ إضافة</a>
      </div>
      <div class="divide-y divide-gray-50">
        <?php if (empty($recentArticles)): ?>
          <p class="text-center text-gray-400 py-6 text-sm">لا توجد مقالات بعد</p>
        <?php else: foreach ($recentArticles as $art): ?>
        <div class="p-4 flex items-center justify-between gap-2">
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate"><?= h($art['title']) ?></p>
            <p class="text-xs text-gray-400 mt-0.5"><?= date('d/m/Y', strtotime($art['created_at'])) ?></p>
          </div>
          <?= statusBadge($art['status']) ?>
        </div>
        <?php endforeach; endif; ?>
      </div>
      <div class="p-4 border-t border-gray-50">
        <a href="/writer/articles.php" class="text-sm text-[#F5C518] font-medium">عرض الكل ←</a>
      </div>
    </div>

    <!-- Recent Leads -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
      <div class="p-4 border-b border-gray-50">
        <h2 class="font-bold text-gray-900">آخر العملاء</h2>
      </div>
      <div class="divide-y divide-gray-50">
        <?php if (empty($recentLeads)): ?>
          <p class="text-center text-gray-400 py-6 text-sm">لا توجد عملاء بعد</p>
        <?php else: foreach ($recentLeads as $lead): ?>
        <div class="p-4 flex items-center justify-between gap-2">
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900"><?= h($lead['name']) ?></p>
            <p class="text-xs text-gray-400 mt-0.5"><?= h($lead['article_title'] ?? 'مباشر') ?></p>
          </div>
          <?= statusBadge($lead['status']) ?>
        </div>
        <?php endforeach; endif; ?>
      </div>
      <div class="p-4 border-t border-gray-50">
        <a href="/writer/leads.php" class="text-sm text-[#F5C518] font-medium">عرض الكل ←</a>
      </div>
    </div>
  </div>
</div>
</div>
</body></html>
