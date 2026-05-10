<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin','account_manager');

$status = $_GET['status'] ?? 'all';
$validStatuses = ['all','pending','approved','rejected','needs_edit','draft'];
if (!in_array($status, $validStatuses)) $status = 'all';

try {
    $db = getDB();
    $sql = "SELECT a.*, u.name as author_name, c.name_ar as city_name FROM articles a LEFT JOIN users u ON u.id=a.author_id LEFT JOIN cities c ON c.id=a.city_id WHERE a.is_deleted=0";
    $params = [];
    if ($status !== 'all') { $sql .= " AND a.status=?"; $params[] = $status; }
    $sql .= " ORDER BY a.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $articles = $stmt->fetchAll();

    $counts = [];
    foreach (['all','pending','approved','rejected','needs_edit'] as $s) {
        $q = $s === 'all' ? $db->query("SELECT COUNT(*) FROM articles WHERE is_deleted=0") : $db->prepare("SELECT COUNT(*) FROM articles WHERE status=? AND is_deleted=0");
        if ($s !== 'all') $q->execute([$s]); 
        $counts[$s] = $q->fetchColumn();
    }
} catch (Exception $e) { $articles = []; $counts = []; }
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>إدارة المقالات - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">إدارة المقالات</h1>
  </div>

  <!-- Tabs -->
  <div class="flex gap-1 mb-6 bg-white rounded-xl p-1 shadow-sm border border-gray-100 w-fit">
    <?php
    $tabs = [['all','الكل'],['pending','قيد المراجعة'],['approved','مقبولة'],['rejected','مرفوضة'],['needs_edit','تحتاج تعديل']];
    foreach ($tabs as [$s,$label]):
    ?>
      <a href="?status=<?= $s ?>" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $status===$s ? 'bg-[#F5C518] text-black' : 'text-gray-500 hover:text-gray-900' ?>">
        <?= $label ?> <?php if (isset($counts[$s])): ?><span class="ml-1 text-xs">(<?= $counts[$s] ?>)</span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr>
            <th class="text-right p-4 font-medium">العنوان</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">الكاتب</th>
            <th class="text-right p-4 font-medium hidden lg:table-cell">المدينة</th>
            <th class="text-right p-4 font-medium">الحالة</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">العملاء</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">المشاهدات</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">التاريخ</th>
            <th class="text-right p-4 font-medium">إجراء</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($articles)): ?>
            <tr><td colspan="8" class="text-center text-gray-400 py-8">لا توجد مقالات</td></tr>
          <?php else: foreach ($articles as $art): ?>
            <tr class="hover:bg-gray-50">
              <td class="p-4 max-w-xs">
                <div class="font-medium text-gray-900 truncate"><?= h($art['title']) ?></div>
                <div class="text-xs text-gray-400 mt-0.5">/<?= h($art['slug']) ?></div>
              </td>
              <td class="p-4 text-gray-500 hidden md:table-cell"><?= h($art['author_name']) ?></td>
              <td class="p-4 text-gray-500 hidden lg:table-cell"><?= h($art['city_name'] ?? '-') ?></td>
              <td class="p-4"><?= statusBadge($art['status']) ?></td>
              <td class="p-4 text-gray-500 hidden md:table-cell"><?= $art['lead_count'] ?></td>
              <td class="p-4 text-gray-500 hidden md:table-cell"><?= number_format($art['view_count']) ?></td>
              <td class="p-4 text-gray-400 text-xs hidden md:table-cell"><?= date('d/m/Y', strtotime($art['created_at'])) ?></td>
              <td class="p-4">
                <div class="flex items-center gap-2">
                  <a href="/admin/article-review.php?id=<?= $art['id'] ?>" class="text-xs bg-blue-50 text-blue-600 px-3 py-1 rounded-lg hover:bg-blue-100">مراجعة</a>
                  <form method="POST" action="/api/article-status.php" onsubmit="return confirm('هل تريد حذف هذا المقال؟')">
                    <input type="hidden" name="article_id" value="<?= $art['id'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="text-xs bg-red-50 text-red-600 px-3 py-1 rounded-lg hover:bg-red-100">حذف</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>
</body></html>
