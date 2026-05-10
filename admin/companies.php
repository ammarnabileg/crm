<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin','account_manager');

$tab = $_GET['tab'] ?? 'broker';

try {
    $db = getDB();
    $developers = $db->query("SELECT * FROM developer_companies WHERE is_deleted=0 ORDER BY name")->fetchAll();
    $brokers = $db->query("SELECT bc.*, u.name as manager_name FROM broker_companies bc LEFT JOIN users u ON u.id=bc.account_manager_id WHERE bc.is_deleted=0 ORDER BY bc.name")->fetchAll();
    $managers = $db->query("SELECT id, name FROM users WHERE role IN ('admin','account_manager') AND is_active=1 AND is_deleted=0 ORDER BY name")->fetchAll();
} catch (Exception $e) { $developers = []; $brokers = []; $managers = []; }

$success = flash('success');
$error = flash('error');
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>إدارة الشركات - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <h1 class="text-2xl font-bold text-gray-900 mb-6">إدارة الشركات</h1>
  <?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4 text-sm"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4 text-sm"><?= h($error) ?></div><?php endif; ?>

  <!-- Tabs -->
  <div class="flex gap-1 mb-6 bg-white rounded-xl p-1 shadow-sm border border-gray-100 w-fit">
    <a href="?tab=broker" class="px-4 py-2 rounded-lg text-sm font-medium <?= $tab==='broker' ? 'bg-[#F5C518] text-black' : 'text-gray-500 hover:text-gray-900' ?>">الشركات الوسيطة (<?= count($brokers) ?>)</a>
    <a href="?tab=developer" class="px-4 py-2 rounded-lg text-sm font-medium <?= $tab==='developer' ? 'bg-[#F5C518] text-black' : 'text-gray-500 hover:text-gray-900' ?>">الشركات المطورة (<?= count($developers) ?>)</a>
  </div>

  <?php if ($tab === 'broker'): ?>
  <!-- Broker Companies -->
  <div class="grid md:grid-cols-3 gap-6">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h2 class="font-bold text-gray-900 mb-4">إضافة شركة وسيطة</h2>
      <form method="POST" action="/api/company-save.php" class="space-y-3">
        <input type="hidden" name="type" value="broker">
        <input type="hidden" name="redirect" value="/admin/companies.php?tab=broker">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">الاسم *</label><input type="text" name="name" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">الاسم العربي</label><input type="text" name="name_ar" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف *</label><input type="text" name="phone" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]" dir="ltr"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label><input type="email" name="email" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]" dir="ltr"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">نسبة العمولة (%)</label><input type="number" name="commission_rate" step="0.01" min="0" max="100" value="0" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]"></div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">مدير الحساب</label>
          <select name="account_manager_id" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
            <option value="">بدون</option>
            <?php foreach ($managers as $m): ?><option value="<?= $m['id'] ?>"><?= h($m['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="w-full bg-[#F5C518] text-black font-bold py-2.5 rounded-xl hover:bg-yellow-400">إضافة</button>
      </form>
    </div>
    <div class="md:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr>
            <th class="text-right p-4 font-medium">الاسم</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">الهاتف</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">عمولة</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">المدير</th>
            <th class="text-right p-4 font-medium">إجراء</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($brokers)): ?><tr><td colspan="5" class="text-center text-gray-400 py-6">لا توجد شركات</td></tr>
          <?php else: foreach ($brokers as $bc): ?>
            <tr class="hover:bg-gray-50">
              <td class="p-4 font-medium text-gray-900"><?= h($bc['name']) ?><?php if ($bc['name_ar']): ?><div class="text-xs text-gray-400"><?= h($bc['name_ar']) ?></div><?php endif; ?></td>
              <td class="p-4 text-gray-500 hidden md:table-cell font-mono text-xs" dir="ltr"><?= h($bc['phone']) ?></td>
              <td class="p-4 text-gray-500 hidden md:table-cell"><?= h($bc['commission_rate']) ?>%</td>
              <td class="p-4 text-gray-500 hidden md:table-cell"><?= h($bc['manager_name'] ?? '-') ?></td>
              <td class="p-4">
                <form method="POST" action="/api/company-save.php" onsubmit="return confirm('حذف؟')">
                  <input type="hidden" name="type" value="broker"><input type="hidden" name="id" value="<?= $bc['id'] ?>"><input type="hidden" name="delete" value="1"><input type="hidden" name="redirect" value="/admin/companies.php?tab=broker">
                  <button type="submit" class="text-xs bg-red-50 text-red-600 px-3 py-1 rounded-lg hover:bg-red-100">حذف</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php else: ?>
  <!-- Developer Companies -->
  <div class="grid md:grid-cols-3 gap-6">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h2 class="font-bold text-gray-900 mb-4">إضافة شركة مطورة</h2>
      <form method="POST" action="/api/company-save.php" class="space-y-3">
        <input type="hidden" name="type" value="developer">
        <input type="hidden" name="redirect" value="/admin/companies.php?tab=developer">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">الاسم *</label><input type="text" name="name" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">الاسم العربي</label><input type="text" name="name_ar" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">رابط الشعار</label><input type="url" name="logo" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]" dir="ltr"></div>
        <button type="submit" class="w-full bg-[#F5C518] text-black font-bold py-2.5 rounded-xl hover:bg-yellow-400">إضافة</button>
      </form>
    </div>
    <div class="md:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr><th class="text-right p-4 font-medium">الاسم</th><th class="text-right p-4 font-medium hidden md:table-cell">الاسم العربي</th><th class="text-right p-4 font-medium">إجراء</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($developers)): ?><tr><td colspan="3" class="text-center text-gray-400 py-6">لا توجد شركات</td></tr>
          <?php else: foreach ($developers as $dev): ?>
            <tr class="hover:bg-gray-50">
              <td class="p-4 font-medium text-gray-900"><?= h($dev['name']) ?></td>
              <td class="p-4 text-gray-500 hidden md:table-cell"><?= h($dev['name_ar'] ?? '-') ?></td>
              <td class="p-4">
                <form method="POST" action="/api/company-save.php" onsubmit="return confirm('حذف؟')">
                  <input type="hidden" name="type" value="developer"><input type="hidden" name="id" value="<?= $dev['id'] ?>"><input type="hidden" name="delete" value="1"><input type="hidden" name="redirect" value="/admin/companies.php?tab=developer">
                  <button type="submit" class="text-xs bg-red-50 text-red-600 px-3 py-1 rounded-lg hover:bg-red-100">حذف</button>
                </form>
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
