<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/', 'writer');
$user = currentUser();

// Handle soft delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delId = intval($_POST['delete_id']);
    try {
        $db = getDB();
        $db->prepare("UPDATE articles SET is_deleted=1 WHERE id=? AND author_id=?")->execute([$delId, $user['id']]);
        auditLog('Article', $delId, 'soft_delete');
        flash('success', 'تم حذف المقال');
    } catch (Exception $e) {}
    header('Location: /writer/articles.php'); exit;
}

try {
    $db = getDB();
    $articles = $db->prepare("SELECT a.*, c.name_ar as city_name, p.name as project_name FROM articles a LEFT JOIN cities c ON c.id=a.city_id LEFT JOIN projects p ON p.id=a.project_id WHERE a.author_id=? AND a.is_deleted=0 ORDER BY a.created_at DESC");
    $articles->execute([$user['id']]);
    $articles = $articles->fetchAll();
} catch (Exception $e) { $articles = []; }
$success = flash('success');
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>مقالاتي - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-writer.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">مقالاتي</h1>
      <p class="text-gray-500 text-sm mt-1">إدارة وتتبع مقالاتك</p>
    </div>
    <a href="/writer/article-new.php" class="bg-[#F5C518] text-black font-bold px-4 py-2 rounded-xl hover:bg-yellow-400">+ مقالة جديدة</a>
  </div>

  <?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4 text-sm"><?= h($success) ?></div>
  <?php endif; ?>

  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-gray-600">
        <tr>
          <th class="text-right p-4 font-medium">العنوان</th>
          <th class="text-right p-4 font-medium hidden md:table-cell">المدينة</th>
          <th class="text-right p-4 font-medium hidden md:table-cell">المشروع</th>
          <th class="text-right p-4 font-medium">الحالة</th>
          <th class="text-right p-4 font-medium hidden md:table-cell">العملاء</th>
          <th class="text-right p-4 font-medium hidden md:table-cell">التاريخ</th>
          <th class="text-right p-4 font-medium">إجراء</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php if (empty($articles)): ?>
          <tr><td colspan="7" class="text-center text-gray-400 py-8">لا توجد مقالات بعد. <a href="/writer/article-new.php" class="text-[#F5C518] font-medium">أنشئ الأولى!</a></td></tr>
        <?php else: foreach ($articles as $art): ?>
          <tr class="hover:bg-gray-50">
            <td class="p-4">
              <div class="font-medium text-gray-900 max-w-xs truncate"><?= h($art['title']) ?></div>
              <?php if ($art['status'] === 'rejected' && $art['rejection_reason']): ?>
                <div class="text-xs text-red-500 mt-0.5">سبب الرفض: <?= h(mb_substr($art['rejection_reason'], 0, 60)) ?>...</div>
              <?php endif; ?>
            </td>
            <td class="p-4 text-gray-500 hidden md:table-cell"><?= h($art['city_name'] ?? '-') ?></td>
            <td class="p-4 text-gray-500 hidden md:table-cell max-w-[120px] truncate"><?= h($art['project_name'] ?? '-') ?></td>
            <td class="p-4"><?= statusBadge($art['status']) ?></td>
            <td class="p-4 text-gray-500 hidden md:table-cell"><?= $art['lead_count'] ?></td>
            <td class="p-4 text-gray-400 text-xs hidden md:table-cell"><?= date('d/m/Y', strtotime($art['created_at'])) ?></td>
            <td class="p-4">
              <div class="flex items-center gap-2">
                <?php if (in_array($art['status'], ['draft','rejected','needs_edit'])): ?>
                  <a href="/writer/article-new.php?id=<?= $art['id'] ?>" class="text-xs bg-blue-50 text-blue-600 px-3 py-1 rounded-lg hover:bg-blue-100">تعديل</a>
                <?php endif; ?>
                <?php if ($art['status'] === 'approved'): ?>
                  <a href="/article.php?slug=<?= h($art['slug']) ?>" target="_blank" class="text-xs bg-green-50 text-green-600 px-3 py-1 rounded-lg hover:bg-green-100">عرض</a>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا المقال؟')">
                  <input type="hidden" name="delete_id" value="<?= $art['id'] ?>">
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
</body></html>
