<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/', 'writer');
$user = currentUser();

try {
    $db = getDB();
    $pendingBal = $db->prepare("SELECT SUM(amount) FROM commissions WHERE writer_id=? AND status='pending' AND is_deleted=0"); $pendingBal->execute([$user['id']]); $pendingBal = (float)($pendingBal->fetchColumn() ?? 0);
    $approvedBal = $db->prepare("SELECT SUM(amount) FROM commissions WHERE writer_id=? AND status IN ('approved','payable') AND is_deleted=0"); $approvedBal->execute([$user['id']]); $approvedBal = (float)($approvedBal->fetchColumn() ?? 0);
    $paidBal = $db->prepare("SELECT SUM(amount) FROM commissions WHERE writer_id=? AND status='paid' AND is_deleted=0"); $paidBal->execute([$user['id']]); $paidBal = (float)($paidBal->fetchColumn() ?? 0);
    $commissions = $db->prepare("SELECT c.*, l.name as lead_name, d.sale_amount FROM commissions c LEFT JOIN leads l ON l.id=c.lead_id LEFT JOIN deals d ON d.id=c.deal_id WHERE c.writer_id=? AND c.is_deleted=0 ORDER BY c.created_at DESC");
    $commissions->execute([$user['id']]);
    $commissions = $commissions->fetchAll();
    $payableComms = array_filter($commissions, fn($c) => $c['status'] === 'payable');
} catch (Exception $e) { $pendingBal=$approvedBal=$paidBal=0; $commissions=$payableComms=[]; }

$payoutError = flash('payout_error');
$payoutSuccess = flash('payout_success');
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>المحفظة - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-writer.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">المحفظة والعمولات</h1>
    <p class="text-gray-500 text-sm mt-1">تتبع أرباحك واطلب السحب</p>
  </div>

  <?php if ($payoutSuccess): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4 text-sm"><?= h($payoutSuccess) ?></div><?php endif; ?>
  <?php if ($payoutError): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4 text-sm"><?= h($payoutError) ?></div><?php endif; ?>

  <!-- Balance Cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
    <div class="bg-yellow-50 border border-yellow-100 rounded-2xl p-5">
      <div class="text-2xl font-black text-yellow-600"><?= formatMoney($pendingBal) ?></div>
      <div class="text-sm text-yellow-700 mt-1">عمولات قيد المراجعة</div>
    </div>
    <div class="bg-[#F5C518] rounded-2xl p-5">
      <div class="text-2xl font-black text-gray-900"><?= formatMoney($approvedBal) ?></div>
      <div class="text-sm text-gray-800 mt-1">رصيد جاهز للسحب</div>
    </div>
    <div class="bg-green-50 border border-green-100 rounded-2xl p-5">
      <div class="text-2xl font-black text-green-600"><?= formatMoney($paidBal) ?></div>
      <div class="text-sm text-green-700 mt-1">إجمالي المدفوع</div>
    </div>
  </div>

  <!-- Payout Request -->
  <?php if (!empty($payableComms) && $approvedBal > 0): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
    <h2 class="font-bold text-gray-900 mb-4">طلب سحب رصيد</h2>
    <form method="POST" action="/api/payout-request.php" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">المبلغ المتاح: <?= formatMoney($approvedBal) ?></label>
        <input type="number" name="amount" step="0.01" max="<?= $approvedBal ?>" value="<?= $approvedBal ?>" required class="w-full md:w-64 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">بيانات البنك أو المحفظة</label>
        <textarea name="bank_details" rows="3" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]" placeholder="اسم البنك، رقم الحساب، اسم صاحب الحساب..."></textarea>
      </div>
      <button type="submit" class="bg-[#F5C518] text-black font-bold px-6 py-2.5 rounded-xl hover:bg-yellow-400">طلب السحب</button>
    </form>
  </div>
  <?php endif; ?>

  <!-- Commissions Table -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-4 border-b border-gray-50">
      <h2 class="font-bold text-gray-900">سجل العمولات</h2>
    </div>
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-gray-600">
        <tr>
          <th class="text-right p-4 font-medium">العميل</th>
          <th class="text-right p-4 font-medium hidden md:table-cell">قيمة الصفقة</th>
          <th class="text-right p-4 font-medium">مبلغ العمولة</th>
          <th class="text-right p-4 font-medium">الحالة</th>
          <th class="text-right p-4 font-medium hidden md:table-cell">التاريخ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php if (empty($commissions)): ?>
          <tr><td colspan="5" class="text-center text-gray-400 py-8">لا توجد عمولات حتى الآن</td></tr>
        <?php else: foreach ($commissions as $comm): ?>
          <tr class="hover:bg-gray-50">
            <td class="p-4 font-medium text-gray-900"><?= h($comm['lead_name'] ?? '-') ?></td>
            <td class="p-4 text-gray-500 hidden md:table-cell"><?= $comm['sale_amount'] ? formatMoney((float)$comm['sale_amount']) : '-' ?></td>
            <td class="p-4 font-bold text-green-600"><?= formatMoney((float)$comm['amount']) ?></td>
            <td class="p-4"><?= statusBadge($comm['status']) ?></td>
            <td class="p-4 text-gray-400 text-xs hidden md:table-cell"><?= date('d/m/Y', strtotime($comm['created_at'])) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
</body></html>
