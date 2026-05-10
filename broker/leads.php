<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/', 'broker');
$user = currentUser();

try {
    $db = getDB();
    $companyStmt = $db->prepare("SELECT company_id FROM broker_company_staff WHERE user_id=? LIMIT 1");
    $companyStmt->execute([$user['id']]);
    $companyId = $companyStmt->fetchColumn();

    if ($companyId) {
        $leads = $db->prepare("SELECT l.*, c.name_ar as city_name, p.name as project_name FROM leads l LEFT JOIN cities c ON c.id=l.city_id LEFT JOIN projects p ON p.id=l.project_id WHERE l.broker_company_id=? AND l.is_deleted=0 ORDER BY l.created_at DESC");
        $leads->execute([$companyId]);
        $leads = $leads->fetchAll();
    } else { $leads = []; }
} catch (Exception $e) { $leads = []; $companyId = null; }
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>العملاء المحالون - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-broker.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <h1 class="text-2xl font-bold text-gray-900 mb-6">العملاء المحالون</h1>

  <?php if (!$companyId): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-6 text-center">
      <p class="text-yellow-800">لم يتم تعيينك لشركة وسيطة. تواصل مع الإدارة.</p>
    </div>
  <?php else: ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr>
            <th class="text-right p-4 font-medium">الاسم</th>
            <th class="text-right p-4 font-medium">الهاتف</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">المرحلة</th>
            <th class="text-right p-4 font-medium">الدرجة</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">المدينة</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">التاريخ</th>
            <th class="text-right p-4 font-medium">إجراء</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($leads)): ?>
            <tr><td colspan="7" class="text-center text-gray-400 py-8">لا توجد عملاء محالون بعد</td></tr>
          <?php else: foreach ($leads as $lead): ?>
            <tr class="hover:bg-gray-50">
              <td class="p-4 font-medium text-gray-900"><?= h($lead['name']) ?></td>
              <td class="p-4 font-mono text-gray-500 text-xs" dir="ltr"><?= h($lead['phone']) ?></td>
              <td class="p-4 hidden md:table-cell"><?= statusBadge($lead['pipeline_stage']) ?></td>
              <td class="p-4"><?= statusBadge($lead['score']) ?></td>
              <td class="p-4 text-gray-500 hidden md:table-cell"><?= h($lead['city_name'] ?? '-') ?></td>
              <td class="p-4 text-gray-400 text-xs hidden md:table-cell"><?= date('d/m/Y', strtotime($lead['created_at'])) ?></td>
              <td class="p-4">
                <a href="/broker/lead-detail.php?id=<?= $lead['id'] ?>" class="text-xs bg-[#F5C518] text-black px-3 py-1 rounded-lg font-medium hover:bg-yellow-400">تفاصيل</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
</div>
</body></html>
