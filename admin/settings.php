<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin');

try {
    $db = getDB();
    $faqs = $db->query("SELECT * FROM global_faqs WHERE is_active=1 ORDER BY sort_order")->fetchAll();
    $settings = $db->query("SELECT * FROM settings ORDER BY `group`, `key`")->fetchAll();
    $settingsMap = array_column($settings, 'value', 'key');
} catch (Exception $e) { $faqs = []; $settingsMap = []; }

$success = flash('success');
$error = flash('error');
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>الإعدادات - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <h1 class="text-2xl font-bold text-gray-900 mb-6">إعدادات الموقع</h1>
  <?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4 text-sm"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4 text-sm"><?= h($error) ?></div><?php endif; ?>

  <div class="grid md:grid-cols-2 gap-6">
    <!-- Site Settings -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h2 class="font-bold text-gray-900 mb-4">الإعدادات العامة</h2>
      <form method="POST" action="/api/faq-save.php" class="space-y-4">
        <input type="hidden" name="action" value="settings">
        <input type="hidden" name="redirect" value="/admin/settings.php">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">اسم الموقع</label>
          <input type="text" name="site_name" value="<?= h($settingsMap['site_name'] ?? 'مربح') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">رقم المبيعات</label>
          <input type="text" name="sales_phone" value="<?= h($settingsMap['sales_phone'] ?? '0123456789') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]" dir="ltr">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">رقم واتساب</label>
          <input type="text" name="whatsapp_number" value="<?= h($settingsMap['whatsapp_number'] ?? '0123456789') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]" dir="ltr">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">نسبة العمولة للكتاب</label>
          <div class="flex items-center gap-2">
            <input type="number" name="commission_rate" value="<?= h($settingsMap['commission_rate'] ?? '0.20') ?>" step="0.01" min="0" max="1" class="w-32 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
            <span class="text-xs text-gray-400">(0.20 = 20%)</span>
          </div>
        </div>
        <button type="submit" class="w-full bg-[#F5C518] text-black font-bold py-2.5 rounded-xl hover:bg-yellow-400">حفظ الإعدادات</button>
      </form>
    </div>

    <!-- Global FAQs -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h2 class="font-bold text-gray-900 mb-4">الأسئلة الشائعة العامة</h2>
      <!-- Add FAQ Form -->
      <form method="POST" action="/api/faq-save.php" class="space-y-3 mb-6 pb-6 border-b border-gray-100">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="redirect" value="/admin/settings.php">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">السؤال *</label><input type="text" name="question" required class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">الإجابة *</label><textarea name="answer" rows="3" required class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]"></textarea></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">ترتيب العرض</label><input type="number" name="sort_order" value="0" class="w-24 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]"></div>
        <button type="submit" class="w-full bg-[#F5C518] text-black font-bold py-2 rounded-xl hover:bg-yellow-400 text-sm">إضافة سؤال</button>
      </form>

      <!-- FAQs List -->
      <div class="space-y-3">
        <?php if (empty($faqs)): ?>
          <p class="text-gray-400 text-sm">لا توجد أسئلة</p>
        <?php else: foreach ($faqs as $faq): ?>
          <div class="bg-gray-50 rounded-xl p-3">
            <div class="flex items-start justify-between gap-2">
              <div class="flex-1">
                <p class="text-sm font-medium text-gray-900"><?= h($faq['question']) ?></p>
                <p class="text-xs text-gray-500 mt-1"><?= h(mb_substr($faq['answer'], 0, 100)) ?><?= mb_strlen($faq['answer']) > 100 ? '...' : '' ?></p>
              </div>
              <form method="POST" action="/api/faq-save.php" onsubmit="return confirm('حذف؟')">
                <input type="hidden" name="id" value="<?= $faq['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="redirect" value="/admin/settings.php">
                <button type="submit" class="text-xs bg-red-50 text-red-600 px-2 py-1 rounded-lg hover:bg-red-100 flex-shrink-0">حذف</button>
              </form>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>
</div>
</body></html>
