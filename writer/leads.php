<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/', 'writer');
$user = currentUser();

try {
    $db = getDB();
    $leads = $db->prepare("SELECT l.*, a.title as article_title, c.name_ar as city_name FROM leads l LEFT JOIN articles a ON a.id=l.article_id LEFT JOIN cities c ON c.id=l.city_id WHERE l.writer_id=? AND l.is_deleted=0 ORDER BY l.created_at DESC");
    $leads->execute([$user['id']]);
    $leads = $leads->fetchAll();
} catch (Exception $e) { $leads = []; }
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>متابعة العملاء - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-writer.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">متابعة العملاء</h1>
    <p class="text-gray-500 text-sm mt-1">العملاء الذين جاءوا من خلال مقالاتك</p>
  </div>

  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr>
            <th class="text-right p-4 font-medium">الاسم</th>
            <th class="text-right p-4 font-medium">الهاتف</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">المصدر</th>
            <th class="text-right p-4 font-medium">الحالة</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">الدرجة</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">المقالة</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">التاريخ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($leads)): ?>
            <tr><td colspan="7" class="text-center text-gray-400 py-8">لا توجد عملاء حتى الآن. انشر مقالاتك لجذب العملاء!</td></tr>
          <?php else: foreach ($leads as $lead): ?>
            <tr class="hover:bg-gray-50">
              <td class="p-4 font-medium text-gray-900"><?= h($lead['name']) ?></td>
              <td class="p-4 text-gray-500 font-mono" dir="ltr"><?= h($lead['phone']) ?></td>
              <td class="p-4 hidden md:table-cell"><?= statusBadge($lead['source']) ?></td>
              <td class="p-4"><?= statusBadge($lead['status']) ?></td>
              <td class="p-4 hidden md:table-cell"><?= statusBadge($lead['score']) ?></td>
              <td class="p-4 text-gray-400 text-xs hidden md:table-cell max-w-[150px] truncate"><?= h($lead['article_title'] ?? '-') ?></td>
              <td class="p-4 text-gray-400 text-xs hidden md:table-cell"><?= date('d/m/Y', strtotime($lead['created_at'])) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>
</body></html>
