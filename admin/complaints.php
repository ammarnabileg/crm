<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin','account_manager');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_id'])) {
    $rid = intval($_POST['resolve_id']);
    $resolution = trim($_POST['resolution'] ?? '');
    try {
        $db = getDB();
        $db->prepare("UPDATE complaints SET status='resolved', resolution=?, resolved_by=?, resolved_at=NOW() WHERE id=?")->execute([$resolution, currentUser()['id'], $rid]);
        flash('success', 'تم حل الشكوى بنجاح');
    } catch (Exception $e) {}
    header('Location: /admin/complaints.php'); exit;
}

try {
    $db = getDB();
    $complaints = $db->query("SELECT c.*, u.name as user_name FROM complaints c LEFT JOIN users u ON u.id=c.user_id WHERE c.is_deleted=0 ORDER BY c.created_at DESC")->fetchAll();
} catch (Exception $e) { $complaints = []; }
$success = flash('success');
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>الشكاوي - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <h1 class="text-2xl font-bold text-gray-900 mb-6">إدارة الشكاوي</h1>
  <?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4 text-sm"><?= h($success) ?></div><?php endif; ?>

  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-gray-600">
        <tr>
          <th class="text-right p-4 font-medium">العنوان</th>
          <th class="text-right p-4 font-medium hidden md:table-cell">المستخدم</th>
          <th class="text-right p-4 font-medium hidden md:table-cell">النوع</th>
          <th class="text-right p-4 font-medium">الأولوية</th>
          <th class="text-right p-4 font-medium">الحالة</th>
          <th class="text-right p-4 font-medium hidden md:table-cell">التاريخ</th>
          <th class="text-right p-4 font-medium">إجراء</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php if (empty($complaints)): ?>
          <tr><td colspan="7" class="text-center text-gray-400 py-8">لا توجد شكاوي</td></tr>
        <?php else: foreach ($complaints as $c): ?>
          <tr class="hover:bg-gray-50" x-data="{ open: false }">
            <td class="p-4">
              <div class="font-medium text-gray-900"><?= h($c['title']) ?></div>
              <div class="text-xs text-gray-400 mt-0.5 max-w-xs truncate"><?= h(mb_substr($c['description'], 0, 80)) ?></div>
            </td>
            <td class="p-4 text-gray-500 hidden md:table-cell"><?= h($c['user_name']) ?></td>
            <td class="p-4 text-gray-500 text-xs hidden md:table-cell"><?= h($c['type']) ?></td>
            <td class="p-4"><?= statusBadge($c['priority']) ?></td>
            <td class="p-4"><?= statusBadge($c['status']) ?></td>
            <td class="p-4 text-gray-400 text-xs hidden md:table-cell"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
            <td class="p-4">
              <?php if ($c['status'] === 'open' || $c['status'] === 'in_review'): ?>
              <button @click="open = !open" class="text-xs bg-blue-50 text-blue-600 px-3 py-1 rounded-lg hover:bg-blue-100">حل</button>
              <div x-show="open" class="mt-2">
                <form method="POST" class="flex gap-2">
                  <input type="hidden" name="resolve_id" value="<?= $c['id'] ?>">
                  <input type="text" name="resolution" placeholder="قرار الحل..." required class="border border-gray-200 rounded-xl px-2 py-1 text-xs focus:outline-none focus:border-[#F5C518]">
                  <button type="submit" class="bg-green-500 text-white text-xs px-2 py-1 rounded-lg">تأكيد</button>
                </form>
              </div>
              <?php else: ?>
                <?php if ($c['resolution']): ?><span class="text-xs text-gray-400 italic"><?= h(mb_substr($c['resolution'], 0, 40)) ?></span><?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
</body></html>
