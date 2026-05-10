<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin','account_manager');

try {
    $db = getDB();
    $projects = $db->query("SELECT p.*, d.name as developer_name, c.name_ar as city_name FROM projects p LEFT JOIN developer_companies d ON d.id=p.developer_id LEFT JOIN cities c ON c.id=p.city_id WHERE p.is_deleted=0 ORDER BY p.created_at DESC")->fetchAll();
} catch (Exception $e) { $projects = []; }
$success = flash('success');
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>المشاريع - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">إدارة المشاريع</h1>
    <a href="/admin/project-form.php" class="bg-[#F5C518] text-black font-bold px-4 py-2 rounded-xl hover:bg-yellow-400">+ مشروع جديد</a>
  </div>
  <?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4 text-sm"><?= h($success) ?></div><?php endif; ?>

  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-gray-600">
        <tr>
          <th class="text-right p-4 font-medium">المشروع</th>
          <th class="text-right p-4 font-medium hidden md:table-cell">المطور</th>
          <th class="text-right p-4 font-medium hidden md:table-cell">المدينة</th>
          <th class="text-right p-4 font-medium">الحالة</th>
          <th class="text-right p-4 font-medium hidden md:table-cell">السعر</th>
          <th class="text-right p-4 font-medium">إجراء</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php if (empty($projects)): ?>
          <tr><td colspan="6" class="text-center text-gray-400 py-8">لا توجد مشاريع</td></tr>
        <?php else: foreach ($projects as $p): ?>
          <tr class="hover:bg-gray-50">
            <td class="p-4">
              <div class="font-medium text-gray-900"><?= h($p['name']) ?></div>
              <?php if ($p['name_ar'] && $p['name_ar'] !== $p['name']): ?><div class="text-xs text-gray-400"><?= h($p['name_ar']) ?></div><?php endif; ?>
            </td>
            <td class="p-4 text-gray-500 hidden md:table-cell"><?= h($p['developer_name'] ?? '-') ?></td>
            <td class="p-4 text-gray-500 hidden md:table-cell"><?= h($p['city_name'] ?? '-') ?></td>
            <td class="p-4"><?= $p['is_published'] ? '<span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">منشور</span>' : '<span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600">مخفي</span>' ?></td>
            <td class="p-4 text-gray-500 hidden md:table-cell"><?= $p['min_price'] ? formatMoney((float)$p['min_price']) : '-' ?></td>
            <td class="p-4">
              <div class="flex items-center gap-2">
                <a href="/admin/project-form.php?id=<?= $p['id'] ?>" class="text-xs bg-blue-50 text-blue-600 px-3 py-1 rounded-lg hover:bg-blue-100">تعديل</a>
                <form method="POST" action="/api/project-save.php">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <input type="hidden" name="toggle_publish" value="1">
                  <input type="hidden" name="redirect" value="/admin/projects.php">
                  <button type="submit" class="text-xs bg-yellow-50 text-yellow-600 px-3 py-1 rounded-lg hover:bg-yellow-100"><?= $p['is_published'] ? 'إخفاء' : 'نشر' ?></button>
                </form>
                <form method="POST" action="/api/project-save.php" onsubmit="return confirm('حذف المشروع؟')">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <input type="hidden" name="delete" value="1">
                  <input type="hidden" name="redirect" value="/admin/projects.php">
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
