<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin','account_manager');

try {
    $db = getDB();
    $cities = $db->query("SELECT c.*, co.name_ar as country_name FROM cities c LEFT JOIN countries co ON co.id=c.country_id WHERE c.is_deleted=0 ORDER BY c.created_at DESC")->fetchAll();
    $countries = $db->query("SELECT * FROM countries WHERE is_active=1 ORDER BY name_ar")->fetchAll();
} catch (Exception $e) { $cities = []; $countries = []; }

$success = flash('success');
$error = flash('error');
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>إدارة المدن - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <h1 class="text-2xl font-bold text-gray-900 mb-6">إدارة المدن</h1>
  <?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4 text-sm"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4 text-sm"><?= h($error) ?></div><?php endif; ?>

  <div class="grid md:grid-cols-3 gap-6">
    <!-- Add Form -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h2 class="font-bold text-gray-900 mb-4">إضافة مدينة جديدة</h2>
      <form method="POST" action="/api/city-save.php" class="space-y-4">
        <input type="hidden" name="redirect" value="/admin/cities.php">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">الاسم (عربي) *</label><input type="text" name="name_ar" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">الاسم (إنجليزي) *</label><input type="text" name="name" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]" dir="ltr"></div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">الدولة</label>
          <select name="country_id" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
            <?php foreach ($countries as $co): ?><option value="<?= $co['id'] ?>"><?= h($co['name_ar']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">رابط الصورة</label><input type="url" name="image" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]" dir="ltr"></div>
        <button type="submit" class="w-full bg-[#F5C518] text-black font-bold py-2.5 rounded-xl hover:bg-yellow-400">إضافة المدينة</button>
      </form>
    </div>

    <!-- Cities List -->
    <div class="md:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="p-4 border-b border-gray-50"><h2 class="font-bold text-gray-900">المدن (<?= count($cities) ?>)</h2></div>
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr>
            <th class="text-right p-4 font-medium">الاسم العربي</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">الاسم الإنجليزي</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">الدولة</th>
            <th class="text-right p-4 font-medium">الحالة</th>
            <th class="text-right p-4 font-medium">إجراء</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($cities)): ?>
            <tr><td colspan="5" class="text-center text-gray-400 py-6">لا توجد مدن</td></tr>
          <?php else: foreach ($cities as $city): ?>
            <tr class="hover:bg-gray-50">
              <td class="p-4 font-medium text-gray-900"><?= h($city['name_ar']) ?></td>
              <td class="p-4 text-gray-500 hidden md:table-cell" dir="ltr"><?= h($city['name']) ?></td>
              <td class="p-4 text-gray-500 hidden md:table-cell"><?= h($city['country_name'] ?? '-') ?></td>
              <td class="p-4"><?= $city['is_active'] ? '<span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">نشطة</span>' : '<span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full">معطلة</span>' ?></td>
              <td class="p-4">
                <form method="POST" action="/api/city-save.php" onsubmit="return confirm('حذف؟')">
                  <input type="hidden" name="id" value="<?= $city['id'] ?>">
                  <input type="hidden" name="delete" value="1">
                  <input type="hidden" name="redirect" value="/admin/cities.php">
                  <button type="submit" class="text-xs bg-red-50 text-red-600 px-3 py-1 rounded-lg hover:bg-red-100">حذف</button>
                </form>
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
