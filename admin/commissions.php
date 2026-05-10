<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin','account_manager');

try {
    $db = getDB();
    $commissions = $db->query("SELECT c.*, u.name as writer_name, l.name as lead_name, d.sale_amount FROM commissions c LEFT JOIN users u ON u.id=c.writer_id LEFT JOIN leads l ON l.id=c.lead_id LEFT JOIN deals d ON d.id=c.deal_id WHERE c.is_deleted=0 ORDER BY c.created_at DESC")->fetchAll();
} catch (Exception $e) { $commissions = []; }
$success = flash('success');
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>العمولات - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <h1 class="text-2xl font-bold text-gray-900 mb-6">إدارة العمولات</h1>
  <?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4 text-sm"><?= h($success) ?></div><?php endif; ?>

  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr>
            <th class="text-right p-4 font-medium">الكاتب</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">العميل</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">قيمة الصفقة</th>
            <th class="text-right p-4 font-medium">مبلغ العمولة</th>
            <th class="text-right p-4 font-medium">الحالة</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">التاريخ</th>
            <th class="text-right p-4 font-medium">إجراء</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($commissions)): ?>
            <tr><td colspan="7" class="text-center text-gray-400 py-8">لا توجد عمولات</td></tr>
          <?php else: foreach ($commissions as $c): ?>
            <tr class="hover:bg-gray-50">
              <td class="p-4 font-medium text-gray-900"><?= h($c['writer_name']) ?></td>
              <td class="p-4 text-gray-500 hidden md:table-cell"><?= h($c['lead_name'] ?? '-') ?></td>
              <td class="p-4 text-gray-500 hidden md:table-cell"><?= $c['sale_amount'] ? formatMoney((float)$c['sale_amount']) : '-' ?></td>
              <td class="p-4 font-bold text-green-600"><?= formatMoney((float)$c['amount']) ?></td>
              <td class="p-4"><?= statusBadge($c['status']) ?></td>
              <td class="p-4 text-gray-400 text-xs hidden md:table-cell"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
              <td class="p-4">
                <div class="flex items-center gap-1">
                  <?php if ($c['status'] === 'pending'): ?>
                    <form method="POST" action="/api/commission-action.php">
                      <input type="hidden" name="commission_id" value="<?= $c['id'] ?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="redirect" value="/admin/commissions.php">
                      <button type="submit" class="text-xs bg-green-50 text-green-600 px-2 py-1 rounded-lg hover:bg-green-100">قبول</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($c['status'] === 'approved'): ?>
                    <form method="POST" action="/api/commission-action.php">
                      <input type="hidden" name="commission_id" value="<?= $c['id'] ?>"><input type="hidden" name="action" value="payable"><input type="hidden" name="redirect" value="/admin/commissions.php">
                      <button type="submit" class="text-xs bg-blue-50 text-blue-600 px-2 py-1 rounded-lg hover:bg-blue-100">جاهز للدفع</button>
                    </form>
                  <?php endif; ?>
                  <?php if (in_array($c['status'], ['pending','approved'])): ?>
                    <form method="POST" action="/api/commission-action.php">
                      <input type="hidden" name="commission_id" value="<?= $c['id'] ?>"><input type="hidden" name="action" value="reject"><input type="hidden" name="redirect" value="/admin/commissions.php">
                      <button type="submit" class="text-xs bg-red-50 text-red-600 px-2 py-1 rounded-lg hover:bg-red-100">رفض</button>
                    </form>
                  <?php endif; ?>
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
